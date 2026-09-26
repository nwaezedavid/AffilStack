<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Credits\CreditManager;

/**
 * Self-service plan upgrade/downgrade (audit item #2). An upgrade is
 * immediate — the user pays now, but only the unused-time credit on their
 * current plan short of the new one's price, not the new plan's full
 * sticker price again. A downgrade is scheduled instead: nothing is
 * charged, and the current (higher) plan's features/credits keep working
 * until the period they already paid for actually ends — see
 * applyPendingChange(), called from SubscriptionRenewalService for Stripe's
 * real auto-renewal and from the subscriptions:apply-pending-downgrades
 * sweep for every other gateway's one-time-charge model.
 */
class PlanChangeService
{
    public function __construct(protected CreditManager $credits) {}

    protected function priceCents(Plan $plan, string $cycle): int
    {
        return $cycle === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents;
    }

    protected function monthlyEquivalentCents(Plan $plan, string $cycle): int
    {
        return $cycle === 'yearly' ? (int) round($plan->price_yearly_cents / 12) : $plan->price_monthly_cents;
    }

    public function isUpgrade(Subscription $subscription, Plan $newPlan, string $newCycle): bool
    {
        if (! $subscription->plan) {
            return true;
        }

        return $this->monthlyEquivalentCents($newPlan, $newCycle)
            > $this->monthlyEquivalentCents($subscription->plan, $subscription->billing_cycle);
    }

    /**
     * The unused-time value of the current period, valued at what the
     * current plan actually cost, credited toward an immediate upgrade —
     * never more than the new plan would cost outright, and never
     * negative. Deliberately always 0 for Stripe: this app only ever
     * creates a brand-new Stripe Checkout subscription on a plan change
     * (it never calls Stripe's own subscription-update API), so a
     * discounted price here would recur at that discount forever rather
     * than just this once. Every other gateway is a one-time charge with
     * no such recurring object to worry about.
     */
    public function prorationCreditCents(Subscription $subscription, Plan $newPlan, string $newCycle, ?string $newGateway = null): int
    {
        // What matters is the gateway the NEW plan will be billed on: a
        // discounted Stripe checkout becomes that subscription's recurring
        // price forever, no matter which gateway the old plan used.
        if ($subscription->gateway === 'stripe' || $newGateway === 'stripe') {
            return 0;
        }

        if (! $subscription->plan || ! $subscription->current_period_start || ! $subscription->current_period_end) {
            return 0;
        }

        if (! $subscription->current_period_end->isFuture()) {
            return 0;
        }

        $unusedCents = $this->unusedValueCents($subscription);
        $newPriceCents = $this->priceCents($newPlan, $newCycle);

        return max(0, min($unusedCents, $newPriceCents));
    }

    protected function unusedValueCents(Subscription $subscription): int
    {
        if (! $subscription->plan || ! $subscription->current_period_start || ! $subscription->current_period_end) {
            return 0;
        }

        $totalDays = max(1, $subscription->current_period_start->diffInDays($subscription->current_period_end));
        $remainingDays = max(0, (int) now()->diffInDays($subscription->current_period_end, false));

        return (int) round(($remainingDays / $totalDays) * $this->priceCents($subscription->plan, $subscription->billing_cycle));
    }

    /**
     * The rare edge case where prorationCreditCents() fully covers the new
     * plan's price (e.g. switching monthly-to-yearly with a lot of unused
     * time banked) — applied directly rather than sending the gateway a
     * $0 checkout, which several of them simply reject.
     */
    public function applyImmediateUpgrade(Subscription $subscription, Plan $newPlan, string $newCycle): void
    {
        $subscription->loadMissing('user', 'plan');

        // The banked credit is converted into time on the new plan at the
        // new plan's price — keeping the old period end would hand out, say,
        // eleven months of a monthly plan for the price of one.
        $newPriceCents = max(1, $this->priceCents($newPlan, $newCycle));
        $cycleDays = $newCycle === 'yearly' ? 365 : 30;
        $unusedCents = $this->unusedValueCents($subscription);
        $days = max($cycleDays, (int) floor(($unusedCents / $newPriceCents) * $cycleDays));

        $subscription->update([
            'plan_id' => $newPlan->id,
            'billing_cycle' => $newCycle,
            'current_period_start' => now(),
            'current_period_end' => now()->addDays($days),
            'pending_plan_id' => null,
            'pending_billing_cycle' => null,
        ]);

        if ($subscription->user) {
            $this->credits->grant($subscription->user, $newPlan->credits_per_month, 'plan_change_grant', $subscription);
            $subscription->update(['last_credit_grant_at' => now()]);
        }
    }

    /**
     * @throws \RuntimeException when Stripe can't be re-priced — the caller
     *                           must not tell the customer it's scheduled
     */
    public function scheduleDowngrade(Subscription $subscription, Plan $newPlan, string $newCycle): void
    {
        // Stripe bills on its own schedule, so the new price has to be set
        // on the Stripe subscription itself (from the next invoice onward).
        if ($subscription->gateway === 'stripe' && $subscription->gateway_subscription_id) {
            app(StripeGateway::class)->updateSubscriptionPrice($subscription->gateway_subscription_id, $newPlan, $newCycle);
        }

        $subscription->update([
            'pending_plan_id' => $newPlan->id,
            'pending_billing_cycle' => $newCycle,
        ]);
    }

    public function cancelScheduledChange(Subscription $subscription): void
    {
        $subscription->loadMissing('plan');

        if ($subscription->pending_plan_id && $subscription->gateway === 'stripe' && $subscription->gateway_subscription_id && $subscription->plan) {
            app(StripeGateway::class)->updateSubscriptionPrice($subscription->gateway_subscription_id, $subscription->plan, $subscription->billing_cycle);
        }

        $subscription->update(['pending_plan_id' => null, 'pending_billing_cycle' => null]);
    }

    /**
     * Called once a subscription's current period has actually elapsed —
     * swaps in the pending plan and grants its credit allotment. A no-op
     * when nothing is scheduled.
     */
    public function applyPendingChange(Subscription $subscription, bool $grantCredits = true): void
    {
        if (! $subscription->pending_plan_id) {
            return;
        }

        $subscription->loadMissing('pendingPlan', 'user');
        $newPlan = $subscription->pendingPlan;

        if (! $newPlan) {
            $subscription->update(['pending_plan_id' => null, 'pending_billing_cycle' => null]);

            return;
        }

        $subscription->update([
            'plan_id' => $newPlan->id,
            'billing_cycle' => $subscription->pending_billing_cycle ?? $subscription->billing_cycle,
            'pending_plan_id' => null,
            'pending_billing_cycle' => null,
        ]);

        // Only a paid renewal (Stripe's invoice.paid) grants the new plan's
        // credits here. On the one-time-charge gateways nothing was paid at
        // period end — the credits come with the customer's next checkout.
        if ($grantCredits && $subscription->user) {
            $this->credits->grant($subscription->user, $newPlan->credits_per_month, 'plan_change_grant', $subscription);
            $subscription->update(['last_credit_grant_at' => now()]);
        }
    }
}
