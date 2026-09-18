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
    public function prorationCreditCents(Subscription $subscription, Plan $newPlan, string $newCycle): int
    {
        if ($subscription->gateway === 'stripe') {
            return 0;
        }

        if (! $subscription->plan || ! $subscription->current_period_start || ! $subscription->current_period_end) {
            return 0;
        }

        $totalDays = max(1, $subscription->current_period_start->diffInDays($subscription->current_period_end));
        $remainingDays = max(0, (int) now()->diffInDays($subscription->current_period_end, false));

        if ($remainingDays <= 0) {
            return 0;
        }

        $currentPaidCents = $this->priceCents($subscription->plan, $subscription->billing_cycle);
        $unusedCents = (int) round(($remainingDays / $totalDays) * $currentPaidCents);
        $newPriceCents = $this->priceCents($newPlan, $newCycle);

        return max(0, min($unusedCents, $newPriceCents));
    }

    /**
     * The rare edge case where prorationCreditCents() fully covers the new
     * plan's price (e.g. switching monthly-to-yearly with a lot of unused
     * time banked) — applied directly rather than sending the gateway a
     * $0 checkout, which several of them simply reject.
     */
    public function applyImmediateUpgrade(Subscription $subscription, Plan $newPlan, string $newCycle): void
    {
        $subscription->loadMissing('user');

        $subscription->update([
            'plan_id' => $newPlan->id,
            'billing_cycle' => $newCycle,
        ]);

        if ($subscription->user) {
            $this->credits->grant($subscription->user, $newPlan->credits_per_month, 'plan_change_grant', $subscription);
        }
    }

    public function scheduleDowngrade(Subscription $subscription, Plan $newPlan, string $newCycle): void
    {
        $subscription->update([
            'pending_plan_id' => $newPlan->id,
            'pending_billing_cycle' => $newCycle,
        ]);
    }

    public function cancelScheduledChange(Subscription $subscription): void
    {
        $subscription->update(['pending_plan_id' => null, 'pending_billing_cycle' => null]);
    }

    /**
     * Called once a subscription's current period has actually elapsed —
     * swaps in the pending plan and grants its credit allotment. A no-op
     * when nothing is scheduled.
     */
    public function applyPendingChange(Subscription $subscription): void
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

        if ($subscription->user) {
            $this->credits->grant($subscription->user, $newPlan->credits_per_month, 'plan_change_grant', $subscription);
        }
    }
}
