<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionRenewalReminder;
use App\Services\Credits\CreditManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Keeps a subscription's local status/current_period_end honest after the
 * initial checkout, for both gateways (task #85):
 *
 *  - Stripe actually auto-renews: handleStripeEvent() reacts to the
 *    recurring-billing webhook events StripeGateway::resolveRenewalEvent()
 *    resolves (a "subscription_cycle" invoice.paid, invoice.payment_failed,
 *    customer.subscription.deleted) and keeps our copy in sync in real
 *    time, from StripeWebhookController.
 *  - Flutterwave's Standard Checkout is a one-time charge with no
 *    recurring billing at all — current_period_end is purely a local
 *    enforcement date that nothing re-charges against. sendRenewalReminders()
 *    emails the customer a few days ahead of it so they can manually
 *    re-checkout from the Billing page.
 *
 * expireLapsed() is the safety net for both gateways: before this existed,
 * nothing ever flipped a subscription's `status` column away from 'active'
 * once current_period_end passed, and User::activeSubscription() only
 * checks `status` — so a lapsed subscription (every Flutterwave one,
 * eventually, and any Stripe one whose renewal webhook was somehow missed)
 * would otherwise stay "active", and every feature gated on it, forever.
 * For Stripe this should rarely ever fire — Stripe retries webhook
 * delivery for days — so if it starts expiring Stripe subscriptions
 * regularly, check the Stripe webhook endpoint is actually configured and
 * reachable rather than assuming Flutterwave-style lapsing is normal there.
 */
class SubscriptionRenewalService
{
    public function __construct(
        protected CreditManager $credits,
        protected PlanChangeService $planChanges,
        protected StripeGateway $stripe,
    ) {}

    /**
     * @param  array{kind: string, gateway_subscription_id: string, gateway_tx_id?: string, amount?: float, currency?: string, raw: array<string, mixed>}  $event
     */
    public function handleStripeEvent(array $event): void
    {
        $subscription = Subscription::where('gateway', 'stripe')
            ->where('gateway_subscription_id', $event['gateway_subscription_id'])
            ->first();

        if (! $subscription) {
            Log::warning('Stripe renewal event for unknown subscription', $event);

            return;
        }

        match ($event['kind']) {
            'renewed' => $this->renew($subscription, $event),
            'payment_failed' => $this->markPastDue($subscription, $event['gateway_invoice_id'] ?? null),
            'canceled' => $this->cancel($subscription),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function renew(Subscription $subscription, array $event): void
    {
        // invoice.paid can be redelivered by Stripe's own webhook retries —
        // the invoice id is a stable idempotency key against double-renewing
        // (extending the period twice, granting credits twice) on a retry.
        if (! empty($event['gateway_tx_id']) && PaymentTransaction::where('gateway_tx_id', $event['gateway_tx_id'])->exists()) {
            return;
        }

        $subscription->loadMissing('plan', 'user');
        $periodEnd = $subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'renewal_reminder_sent_at' => null,
        ]);

        PaymentTransaction::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'type' => 'renewal',
            'gateway' => 'stripe',
            'gateway_tx_id' => $event['gateway_tx_id'] ?? null,
            'tx_ref' => 'renewal_'.Str::uuid(),
            'amount_cents' => (int) round(($event['amount'] ?? 0) * 100),
            'currency' => $event['currency'] ?? $subscription->plan?->currency ?? 'USD',
            'status' => 'successful',
            'raw_payload' => $event['raw'] ?? [],
            'processed_at' => now(),
        ]);

        // A scheduled downgrade (audit item #2) swaps the plan and grants
        // its own credits — never both that AND the old plan's renewal
        // grant for the same cycle.
        if ($subscription->pending_plan_id) {
            $this->planChanges->applyPendingChange($subscription);

            return;
        }

        if ($subscription->plan && $subscription->user) {
            $this->credits->grant($subscription->user, $subscription->plan->credits_per_month, 'renewal_grant', $subscription);
        }
    }

    /**
     * Audit item #2: before giving up on a failed Stripe renewal, try the
     * user's other saved cards against the same invoice — Stripe only ever
     * retries with the card it already had on file, so without this a user
     * with a second card saved would still get interrupted needlessly. A
     * successful retry pays the invoice directly, which makes Stripe fire
     * its own "invoice.paid" webhook a moment later — handled by renew() —
     * so nothing else needs to happen here on success.
     */
    protected function markPastDue(Subscription $subscription, ?string $invoiceId = null): void
    {
        if ($subscription->status === 'past_due') {
            return;
        }

        if ($invoiceId) {
            $alternates = PaymentMethod::where('user_id', $subscription->user_id)
                ->where('gateway', 'stripe')
                ->where('type', 'card')
                ->orderByDesc('last_used_at')
                ->get();

            foreach ($alternates as $method) {
                try {
                    if ($this->stripe->retryInvoiceWithPaymentMethod($invoiceId, $method->gateway_token)) {
                        $method->update(['last_used_at' => now()]);

                        return;
                    }
                } catch (RuntimeException $e) {
                    Log::warning('Stripe renewal retry with alternate card failed', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $subscription->update(['status' => 'past_due']);
        $subscription->user?->notify(new SubscriptionPaymentFailed($subscription));
    }

    protected function cancel(Subscription $subscription): void
    {
        if ($subscription->status === 'canceled') {
            return;
        }

        $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
    }

    /**
     * Reminder sweep for every gateway that doesn't actually auto-renew —
     * Flutterwave, Paystack, and PayPal are all one-time Standard/Orders
     * checkouts here, not real subscriptions. Stripe doesn't need this: it
     * renews itself and tells us via webhook.
     */
    public function sendRenewalReminders(int $daysBefore = 3): int
    {
        $subscriptions = Subscription::query()
            ->whereIn('gateway', ['flutterwave', 'paystack', 'paypal'])
            ->where('status', 'active')
            ->whereNull('renewal_reminder_sent_at')
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [now(), now()->addDays($daysBefore)])
            ->with(['user', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->user) {
                continue;
            }

            $subscription->user->notify(new SubscriptionRenewalReminder($subscription));
            $subscription->update(['renewal_reminder_sent_at' => now()]);
        }

        return $subscriptions->count();
    }

    /**
     * Revokes access from any subscription whose period has actually ended
     * without being renewed. See class docblock for why this is needed at
     * all rather than just relying on `status`.
     */
    public function expireLapsed(): int
    {
        $subscriptions = Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->with('user')
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $subscription->user?->notify(new SubscriptionExpired($subscription));
        }

        return $subscriptions->count();
    }
}
