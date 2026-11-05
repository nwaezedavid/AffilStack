<?php

namespace App\Services\Trials;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TrialInvite;
use App\Models\User;
use App\Notifications\TrialEndingReminder;
use App\Notifications\TrialInviteNotification;
use App\Notifications\TrialPaymentFailed;
use App\Services\Credits\CreditManager;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentMethodRecorder;
use App\Services\Payments\SubscriptionRenewalCharger;
use App\Services\Payments\SubscriptionRenewalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Admin-only 7-day free trials. The trial is never offered on the public
 * site: an admin sends an invite, and the signed, single-use link in that
 * email is the only way to start one.
 *
 *  1. send(): one invite per email address; the link expires in 7 days.
 *  2. startCardCheck(): the invitee saves a card through a small one-time
 *     checkout (the amount is kept as wallet balance and goes toward the
 *     first payment).
 *  3. startTrialFromCardCheck(): once that payment is verified, the
 *     subscription starts as "trialing" with trial_ends_at 7 days ahead.
 *  4. process() (hourly): reminder 2 days before the end, the day-8 charge
 *     (wallet first, then the saved card) or past due + email, and expiry
 *     of unused invites.
 */
class TrialInviteService
{
    public function __construct(
        protected CreditManager $credits,
        protected CustomerWalletManager $wallets,
        protected PaymentMethodRecorder $paymentMethods,
        protected SubscriptionRenewalCharger $charger,
        protected SubscriptionRenewalService $renewals,
    ) {}

    /**
     * Why an invite can't be sent to this address, or null when it can.
     */
    public function blockingReason(string $email): ?string
    {
        $email = mb_strtolower(trim($email));

        $used = TrialInvite::where('email', $email)
            ->whereIn('status', [TrialInvite::STATUS_ACCEPTED, TrialInvite::STATUS_CANCELLED, TrialInvite::STATUS_CONVERTED])
            ->exists();

        if ($used) {
            return 'This email address has already had a free trial.';
        }

        $open = TrialInvite::where('email', $email)
            ->where('status', TrialInvite::STATUS_SENT)
            ->where('expires_at', '>', now())
            ->exists();

        if ($open) {
            return 'This email address already has an unused trial invite.';
        }

        $user = User::where('email', $email)->first();

        if ($user && $user->activeSubscription) {
            return 'This person already has an active plan.';
        }

        return null;
    }

    public function send(string $name, string $email, Plan $plan, string $billingCycle, ?User $admin = null): TrialInvite
    {
        if (! in_array($billingCycle, ['monthly', 'yearly'], true)) {
            throw new InvalidArgumentException('Pick monthly or annual billing.');
        }

        if ($reason = $this->blockingReason($email)) {
            throw new InvalidArgumentException($reason);
        }

        $invite = TrialInvite::create([
            'name' => $name,
            'email' => $email,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'status' => TrialInvite::STATUS_SENT,
            'sent_by_user_id' => $admin?->id,
            'expires_at' => now()->addDays(TrialInvite::TRIAL_DAYS),
        ]);

        Notification::route('mail', [$invite->email => $invite->name])
            ->notify(new TrialInviteNotification($invite, $this->signedUrl($invite)));

        return $invite;
    }

    public function signedUrl(TrialInvite $invite): string
    {
        return URL::temporarySignedRoute('trial-invites.show', $invite->expires_at, ['invite' => $invite->id]);
    }

    /**
     * Gateways that can save a card for the day-8 charge.
     *
     * @param  array<int, PaymentGateway>  $enabledGateways
     * @return array<int, PaymentGateway>
     */
    public function cardGateways(array $enabledGateways): array
    {
        $allowed = (array) config('customer_wallet.trial.card_gateways');

        return array_values(array_filter($enabledGateways, fn (PaymentGateway $gateway) => in_array($gateway->key(), $allowed, true)));
    }

    /**
     * Why this signed-in user can't start this trial, or null when they can.
     */
    public function acceptBlockingReason(TrialInvite $invite, User $user): ?string
    {
        if (! $invite->isRedeemable()) {
            return 'This invite has already been used or has expired.';
        }

        if (mb_strtolower($user->email) !== $invite->email) {
            return "This invite was sent to {$invite->email}. Sign in with that email address to accept it.";
        }

        if ($user->activeSubscription) {
            return 'You already have an active plan, so the free trial isn\'t needed.';
        }

        $alreadyTrialed = TrialInvite::where('email', $invite->email)
            ->whereKeyNot($invite->id)
            ->whereIn('status', [TrialInvite::STATUS_ACCEPTED, TrialInvite::STATUS_CANCELLED, TrialInvite::STATUS_CONVERTED])
            ->exists();

        return $alreadyTrialed ? 'This email address has already had a free trial.' : null;
    }

    /**
     * Starts the one-time checkout that saves the invitee's card.
     *
     * @return array{link: string, tx_ref: string, amount_cents: int, currency: string}
     */
    public function startCardCheck(TrialInvite $invite, User $user, PaymentGateway $gateway): array
    {
        if ($reason = $this->acceptBlockingReason($invite, $user)) {
            throw new InvalidArgumentException($reason);
        }

        $amount = (int) config('customer_wallet.trial.card_check_minor', 100);
        $currency = strtoupper((string) ($invite->plan->currency ?: 'USD'));

        $checkout = $gateway->initiateOneTimeCheckout($user, $amount, $currency, 'Card check for your free trial', [
            'user_id' => $user->id,
            'type' => 'trial_card_check',
            'trial_invite_id' => $invite->id,
            'save_card' => true,
        ]);

        PaymentTransaction::create([
            'user_id' => $user->id,
            'trial_invite_id' => $invite->id,
            'type' => 'trial_card_check',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'plan_id' => $invite->plan_id,
            'billing_cycle' => $invite->billing_cycle,
            'amount_cents' => $checkout['amount_cents'],
            'credited_amount_cents' => $amount,
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return $checkout;
    }

    /**
     * Called by PaymentProcessor once the card-check payment is verified.
     * The paid amount always lands in the wallet. The trial only starts when
     * the gateway returned a reusable card and the invite is still unused.
     *
     * @param  array<string, mixed>  $result
     */
    public function startTrialFromCardCheck(PaymentTransaction $transaction, string $gateway, array $result): ?Subscription
    {
        $user = $transaction->user;
        $invite = $transaction->trialInvite;

        if (! $user || ! $invite) {
            Log::error('Trial card check paid but user or invite could not be resolved', ['tx_ref' => $transaction->tx_ref]);

            return null;
        }

        $method = $this->paymentMethods->record($user, $gateway, $result);
        $canStart = $method && $method->type === 'card' && $this->acceptBlockingReason($invite, $user) === null;
        $subscription = null;

        if ($canStart) {
            $trialEnds = now()->addDays(TrialInvite::TRIAL_DAYS);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $invite->plan_id,
                'status' => 'trialing',
                'billing_cycle' => $invite->billing_cycle,
                'gateway' => $gateway,
                'gateway_customer_id' => $result['customer_reference'] ?? $user->email,
                'payment_method_id' => $method->id,
                'trial_ends_at' => $trialEnds,
                'current_period_start' => now(),
                'current_period_end' => $trialEnds,
                'cancel_at_period_end' => false,
            ]);

            $transaction->update(['subscription_id' => $subscription->id]);
            $user->unsetRelation('activeSubscription');

            $invite->update([
                'status' => TrialInvite::STATUS_ACCEPTED,
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'accepted_at' => now(),
            ]);

            if ($invite->plan) {
                $this->credits->grant($user, $invite->plan->credits_per_month, 'trial_grant', $subscription);
                $subscription->update(['last_credit_grant_at' => now()]);
            }
        }

        $wallet = $this->wallets->walletFor($user);

        if ($transaction->credited_amount_cents && $wallet->currency === strtoupper((string) $invite->plan?->currency)) {
            $this->wallets->credit(
                $user,
                $transaction->credited_amount_cents,
                'card_check',
                'Card check for your free trial — kept as balance',
                $transaction,
                idempotencyKey: 'payment_transaction:'.$transaction->id,
            );
        } else {
            Log::warning('Trial card check not credited to wallet (currency mismatch)', ['tx_ref' => $transaction->tx_ref]);
        }

        return $subscription;
    }

    /**
     * The customer cancels during the trial: nothing is charged, and access
     * ends when the trial would have ended (expireLapsed() handles that).
     */
    public function cancel(Subscription $subscription): void
    {
        if (! $subscription->isTrialing() || $subscription->cancel_at_period_end) {
            return;
        }

        DB::transaction(function () use ($subscription) {
            $subscription->update(['cancel_at_period_end' => true, 'canceled_at' => now()]);

            $subscription->trialInvite?->update([
                'status' => TrialInvite::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }

    /**
     * @return array{reminded: int, converted: int, failed: int, expired: int}
     */
    public function process(): array
    {
        return [
            'reminded' => $this->sendReminders(),
            ...$this->convertDue(),
            'expired' => $this->expireUnusedInvites(),
        ];
    }

    public function sendReminders(): int
    {
        $days = (int) config('customer_wallet.trial.reminder_days_before', 2);

        $subscriptions = Subscription::query()
            ->where('status', 'trialing')
            ->where('cancel_at_period_end', false)
            ->whereNull('trial_reminder_sent_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->with(['user', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->user?->notify(new TrialEndingReminder($subscription));
            $subscription->update(['trial_reminder_sent_at' => now()]);
        }

        return $subscriptions->count();
    }

    /**
     * Day 8: charge the plan — wallet first, then the saved card. A failed
     * charge marks the subscription past due (access pauses) and emails the
     * customer; it expires after a short grace period unless they pay.
     *
     * @return array{converted: int, failed: int}
     */
    public function convertDue(): array
    {
        $stats = ['converted' => 0, 'failed' => 0];

        Subscription::query()
            ->where('status', 'trialing')
            ->where('cancel_at_period_end', false)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->with(['plan', 'user', 'paymentMethod', 'trialInvite'])
            ->each(function (Subscription $subscription) use (&$stats) {
                $result = $this->charger->charge(
                    $subscription,
                    'trial_conversion',
                    'trial_conversion',
                    ($subscription->plan?->name ?? 'AffilStack').' plan — first payment after your free trial',
                );

                if (! $result['success']) {
                    $subscription->update([
                        'status' => 'past_due',
                        'current_period_end' => now()->addDays((int) config('customer_wallet.trial.past_due_grace_days', 3)),
                    ]);
                    $subscription->user?->notify(new TrialPaymentFailed($subscription, $result['message']));
                    $stats['failed']++;

                    return;
                }

                $subscription->update(['current_period_end' => now()]);
                $this->renewals->extendAfterPayment($subscription, $result['transaction'], 'monthly_grant');

                $subscription->trialInvite?->update([
                    'status' => TrialInvite::STATUS_CONVERTED,
                    'converted_at' => now(),
                ]);

                $stats['converted']++;
            });

        return $stats;
    }

    public function expireUnusedInvites(): int
    {
        return TrialInvite::where('status', TrialInvite::STATUS_SENT)
            ->where('expires_at', '<=', now())
            ->update(['status' => TrialInvite::STATUS_EXPIRED]);
    }
}
