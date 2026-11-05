<?php

namespace App\Http\Resources\Mobile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who is signed in to the app and where their account stands: plan,
 * trial, renewal date and credits. Seats report their owner's plan and
 * balances, exactly like the dashboard (billableUser()).
 *
 * @mixin User
 */
class MobileAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billable = $this->billableUser();
        $subscription = $billable->activeSubscription;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatarUrl(),
            'initials' => $this->initials(),
            'locale' => $this->locale,
            'is_seat' => $this->isSeat(),
            'seat_role' => $this->seat_role,
            'on_shared_team_plan' => $this->onSharedTeamPlan(),
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'credits_balance' => (int) $billable->credits_balance,
            'plan' => $subscription ? [
                'name' => $subscription->plan?->name,
                'slug' => $subscription->plan?->slug,
                'status' => $subscription->status,
                'is_trial' => $subscription->status === 'trialing',
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'renews_at' => $subscription->cancel_at_period_end ? null : $subscription->current_period_end?->toIso8601String(),
                'ends_at' => $subscription->cancel_at_period_end ? $subscription->current_period_end?->toIso8601String() : null,
            ] : null,
        ];
    }
}
