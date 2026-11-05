<?php

namespace App\Services\Leads;

use App\Models\LeadRun;
use App\Models\User;

/**
 * The plan's monthly lead allowance (plans.monthly_lead_allowance: 0 = none
 * included, -1 = unlimited). Leads inside it are free; past it, each costs
 * credits (config('credits.costs.lead_found') + 'lead_email_checked').
 */
class LeadAllowance
{
    public function monthly(User $user): int
    {
        return (int) ($user->billableUser()->activeSubscription?->plan?->monthly_lead_allowance ?? 0);
    }

    public function isUnlimited(User $user): bool
    {
        return $this->monthly($user) < 0;
    }

    public function usedThisMonth(User $user): int
    {
        return (int) LeadRun::query()
            ->where('user_id', $user->billableUser()->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('free_leads');
    }

    public function freeRemaining(User $user): int
    {
        if ($this->isUnlimited($user)) {
            return PHP_INT_MAX;
        }

        return max(0, $this->monthly($user) - $this->usedThisMonth($user));
    }

    public function savedToday(User $user): int
    {
        return (int) LeadRun::query()
            ->where('user_id', $user->billableUser()->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('saved');
    }

    public function dailyRemaining(User $user): int
    {
        return max(0, (int) config('leads.daily_cap', 500) - $this->savedToday($user));
    }

    public function costPerPaidLead(): int
    {
        return (int) config('credits.costs.lead_found') + (int) config('credits.costs.lead_email_checked');
    }
}
