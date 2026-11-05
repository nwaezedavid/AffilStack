<?php

namespace App\Services\Agents\Revenue;

use App\Models\AgentActivity;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\UpgradeNudgeNotification;
use App\Services\Dashboard\ProgressCounter;
use App\Services\Leads\LeadAllowance;
use App\Support\Agents\AgentSettings;

/**
 * Upgrade nudge: when a customer reaches the admin's threshold (80% by
 * default) of a plan limit — monthly credits, included leads, CRM contacts
 * or products — the dashboard shows what AffilStack has achieved for them
 * beside an upgrade button, and they get one email per limit per month.
 */
class UpgradeNudge
{
    public function __construct(protected LeadAllowance $leads, protected ProgressCounter $progress) {}

    /**
     * The limits this customer is close to, most used first.
     *
     * @return array<int, array{key: string, label: string, used: int, limit: int, percent: int}>
     */
    public function nearLimits(User $user): array
    {
        if (! RevenueAgentSettings::nudgeEnabled() || $user->isSeat()) {
            return [];
        }

        $plan = $user->activeSubscription?->plan;

        if (! $plan || $this->isTopPlan($plan)) {
            return [];
        }

        $limits = [];

        if ($plan->credits_per_month > 0) {
            $limits[] = ['key' => 'credits', 'label' => 'monthly credits', 'used' => max(0, $plan->credits_per_month - (int) $user->credits_balance), 'limit' => (int) $plan->credits_per_month];
        }

        if ($plan->monthly_lead_allowance > 0) {
            $limits[] = ['key' => 'leads', 'label' => 'included leads', 'used' => $this->leads->usedThisMonth($user), 'limit' => (int) $plan->monthly_lead_allowance];
        }

        if ($plan->contact_limit > 0) {
            $limits[] = ['key' => 'contacts', 'label' => 'CRM contacts', 'used' => $user->crmContacts()->count(), 'limit' => (int) $plan->contact_limit];
        }

        if ($plan->active_products_limit > 0) {
            $limits[] = ['key' => 'products', 'label' => 'products', 'used' => $user->offers()->where('is_sandbox', false)->count(), 'limit' => (int) $plan->active_products_limit];
        }

        $threshold = RevenueAgentSettings::nudgeThreshold();

        return collect($limits)
            ->map(fn (array $limit): array => $limit + ['percent' => (int) min(100, floor($limit['used'] / max(1, $limit['limit']) * 100))])
            ->filter(fn (array $limit): bool => $limit['percent'] >= $threshold)
            ->sortByDesc('percent')
            ->values()
            ->all();
    }

    /**
     * Daily: one email per limit per month.
     */
    public function run(): int
    {
        if (! RevenueAgentSettings::nudgeEnabled()) {
            return 0;
        }

        $sent = 0;

        User::query()->whereNull('agency_owner_id')->where('is_affiliate_only', false)->where('is_suspended', false)
            ->whereHas('activeSubscription', fn ($query) => $query->where('status', 'active'))
            ->with('activeSubscription.plan')
            ->chunkById(200, function ($users) use (&$sent): void {
                foreach ($users as $user) {
                    $limit = $this->nearLimits($user)[0] ?? null;

                    if (! $limit || $this->nudgedThisMonth($user, $limit['key'])) {
                        continue;
                    }

                    $user->notify(new UpgradeNudgeNotification($limit, $this->highlights($user)));
                    AgentSettings::log($user, 'upgrade_nudge', $limit['key'], "Reached {$limit['percent']}% of their {$limit['label']} — sent an upgrade suggestion.", null, $limit);
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * @return array<int, string>
     */
    public function highlights(User $user): array
    {
        return collect($this->progress->for($user)['tiles'])->filter(fn (array $tile): bool => $tile['raw'] > 0)->take(3)
            ->map(fn (array $tile): string => $tile['value'].' '.mb_strtolower($tile['label']))->values()->all();
    }

    protected function nudgedThisMonth(User $user, string $key): bool
    {
        return AgentActivity::query()->where('user_id', $user->id)->where('agent', 'upgrade_nudge')->where('action', $key)
            ->where('created_at', '>=', now()->startOfMonth())->exists();
    }

    protected function isTopPlan(Plan $plan): bool
    {
        return ! Plan::query()->where('is_active', true)->where('price_monthly_cents', '>', $plan->price_monthly_cents)->exists();
    }
}
