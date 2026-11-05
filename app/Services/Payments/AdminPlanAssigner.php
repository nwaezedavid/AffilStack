<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\CreditManager;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An admin giving a manually added customer one complimentary month on a
 * plan, from the Users screen. Nothing is charged: the subscription is
 * active for exactly one month, marked "Assigned by admin", and gets the
 * plan's credits like a paid activation. When the month ends the customer
 * pays to keep it (renewal reminders, then expiry — see
 * SubscriptionRenewalService) or picks another plan.
 */
class AdminPlanAssigner
{
    public const GATEWAY = 'admin';

    public function __construct(protected CreditManager $credits) {}

    /**
     * Why this user can't be given a complimentary month right now, or null
     * when they can. A paying customer's subscription is never replaced.
     */
    public function blockingReason(User $user): ?string
    {
        $current = $user->activeSubscription;

        // Only free plans (an earlier complimentary month, or a review
        // account's "free plan") are ever replaced.
        if ($current && ! $current->assigned_by_admin && $current->gateway !== 'manual') {
            return "{$user->name} already has a {$current->status} {$current->plan?->name} subscription. Cancel it before assigning a complimentary month.";
        }

        return null;
    }

    public function assign(User $user, Plan $plan, string $billingCycle, ?User $admin = null): Subscription
    {
        if (! in_array($billingCycle, ['monthly', 'yearly'], true)) {
            throw new InvalidArgumentException('Pick monthly or annual billing.');
        }

        if ($reason = $this->blockingReason($user)) {
            throw new InvalidArgumentException($reason);
        }

        return DB::transaction(function () use ($user, $plan, $billingCycle, $admin) {
            // A previous complimentary month is replaced by the new one.
            Subscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing'])
                ->where(fn ($query) => $query->where('assigned_by_admin', true)->orWhere('gateway', 'manual'))
                ->update(['status' => 'canceled', 'canceled_at' => now()]);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'gateway' => self::GATEWAY,
                'gateway_customer_id' => $user->email,
                'assigned_by_admin' => true,
                'assigned_by_user_id' => $admin?->id,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'cancel_at_period_end' => false,
            ]);

            $this->credits->grant($user, $plan->credits_per_month, 'admin_assigned_grant', $subscription);
            $subscription->update(['last_credit_grant_at' => now()]);
            $user->unsetRelation('activeSubscription');

            return $subscription;
        });
    }
}
