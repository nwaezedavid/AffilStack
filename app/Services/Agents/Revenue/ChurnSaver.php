<?php

namespace App\Services\Agents\Revenue;

use App\Models\AgentActivity;
use App\Models\LifecycleEmailLog;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\WinBackNotification;
use App\Services\Dashboard\ProgressCounter;
use App\Support\Agents\AgentSettings;

/**
 * Churn saver: once a day, writes personally to paying customers who have
 * gone quiet, and to those whose renewal payment failed, showing what
 * AffilStack has already done for them (from the progress counter) and the
 * admin's optional win-back offer. Each customer hears from it at most
 * once a month (a failed payment: once a fortnight), never in the same week
 * as a lifecycle email.
 */
class ChurnSaver
{
    public function __construct(protected ProgressCounter $progress) {}

    public function run(int $limit = 100): int
    {
        if (! RevenueAgentSettings::churnEnabled()) {
            return 0;
        }

        $sent = 0;

        foreach ($this->failedPayments() as $user) {
            $sent += $this->write($user, 'payment_failed') ? 1 : 0;
        }

        foreach ($this->quietCustomers() as $user) {
            if ($sent >= $limit) {
                break;
            }

            $sent += $this->write($user, 'inactive') ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @return iterable<int, User>
     */
    protected function quietCustomers(): iterable
    {
        $cutoff = now()->subDays(RevenueAgentSettings::churnInactiveDays());

        return User::query()
            ->whereNull('agency_owner_id')
            ->where('is_affiliate_only', false)
            ->where('is_suspended', false)
            ->whereHas('activeSubscription', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $query->where('last_active_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('last_active_at')->where('last_login_at', '<', $cutoff)))
            ->whereDoesntHave('agentActivities', fn ($query) => $query->where('agent', 'churn_saver')->where('created_at', '>=', now()->subDays(30)))
            ->limit(500)
            ->get()
            ->reject(fn (User $user): bool => $this->hadLifecycleEmailRecently($user));
    }

    /**
     * @return iterable<int, User>
     */
    protected function failedPayments(): iterable
    {
        return User::query()
            ->whereIn('id', Subscription::query()->where('status', 'past_due')->where('updated_at', '<=', now()->subDays(2))->select('user_id'))
            ->whereDoesntHave('agentActivities', fn ($query) => $query->where('agent', 'churn_saver')->where('created_at', '>=', now()->subDays(14)))
            ->limit(200)
            ->get();
    }

    protected function hadLifecycleEmailRecently(User $user): bool
    {
        return LifecycleEmailLog::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(7))->exists();
    }

    protected function write(User $user, string $reason): bool
    {
        ProgressCounter::forget($user);
        $progress = $this->progress->for($user);
        $highlights = collect($progress['tiles'])->filter(fn (array $tile): bool => $tile['raw'] > 0)->take(4)
            ->map(fn (array $tile): string => $tile['value'].' '.mb_strtolower($tile['label']))->values()->all();

        $user->notify(new WinBackNotification($reason, $highlights, RevenueAgentSettings::churnOffer()));

        AgentSettings::log($user, 'churn_saver', $reason, $reason === 'payment_failed'
            ? 'Sent a "keep your account" email after the renewal payment failed.'
            : 'Sent a personal check-in after '.RevenueAgentSettings::churnInactiveDays().'+ quiet days.', null, ['highlights' => $highlights], AgentActivity::STATUS_DONE);

        return true;
    }
}
