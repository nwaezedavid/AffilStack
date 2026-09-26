<?php

namespace App\Console\Commands\Subscriptions;

use App\Models\Subscription;
use App\Services\Credits\CreditManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every plan is sold as "N AI credits / month". A monthly subscription gets
 * that at each paid renewal; a yearly one is paid once a year, so this daily
 * sweep tops it up each month in between — one grant per elapsed month,
 * never more than one month's worth per run.
 */
#[Signature('subscriptions:grant-monthly-credits')]
#[Description('Grant the monthly credit allowance to active yearly subscriptions')]
class GrantMonthlyCredits extends Command
{
    public function handle(CreditManager $credits): int
    {
        $granted = 0;

        Subscription::query()
            ->where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->whereNotNull('last_credit_grant_at')
            ->where('last_credit_grant_at', '<=', now()->subMonth())
            ->where(fn ($query) => $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now()))
            ->with(['plan', 'user'])
            ->chunkById(200, function ($subscriptions) use ($credits, &$granted) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->plan || ! $subscription->user) {
                        continue;
                    }

                    $credits->grant($subscription->user, $subscription->plan->credits_per_month, 'monthly_grant', $subscription);

                    // Advance by exactly one month so the grant day stays
                    // stable and a missed day catches up the next run.
                    $subscription->update(['last_credit_grant_at' => $subscription->last_credit_grant_at->copy()->addMonth()]);
                    $granted++;
                }
            });

        $this->info("Granted monthly credits to {$granted} yearly subscription(s).");

        return self::SUCCESS;
    }
}
