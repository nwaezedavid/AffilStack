<?php

namespace App\Console\Commands\Agents;

use App\Models\User;
use App\Services\Agents\Customer\CommissionWatchdog;
use App\Services\Agents\Customer\ContentAutopilot;
use App\Services\Agents\Customer\LeadHunter;
use App\Services\Agents\Customer\OfferScout;
use App\Services\Agents\Customer\WeeklyBriefing;
use App\Services\Agents\Revenue\ChurnSaver;
use App\Services\Agents\Revenue\UpgradeNudge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs one scheduled AI agent: the customer agents go account by account
 * (only plans that include agents, only where the customer switched it on);
 * the revenue agents run once for the whole platform.
 */
#[Signature('agents:run {agent : lead_hunter, commission_watchdog, content_autopilot, offer_scout, weekly_briefing, churn_saver or upgrade_nudge}')]
#[Description('Run one scheduled AI agent')]
class RunCustomerAgent extends Command
{
    /**
     * @var array<string, class-string>
     */
    protected const CUSTOMER_AGENTS = [
        'lead_hunter' => LeadHunter::class,
        'commission_watchdog' => CommissionWatchdog::class,
        'content_autopilot' => ContentAutopilot::class,
        'offer_scout' => OfferScout::class,
        'weekly_briefing' => WeeklyBriefing::class,
    ];

    public function handle(): int
    {
        $agent = (string) $this->argument('agent');

        if ($agent === 'churn_saver') {
            $this->info('Churn saver sent '.app(ChurnSaver::class)->run().' email(s).');

            return self::SUCCESS;
        }

        if ($agent === 'upgrade_nudge') {
            $this->info('Upgrade nudge sent '.app(UpgradeNudge::class)->run().' email(s).');

            return self::SUCCESS;
        }

        $class = self::CUSTOMER_AGENTS[$agent] ?? null;

        if (! $class) {
            $this->error("Unknown agent [{$agent}].");

            return self::FAILURE;
        }

        $service = app($class);
        $accounts = 0;

        User::query()
            ->whereNull('agency_owner_id')
            ->where('is_suspended', false)
            ->whereHas('activeSubscription.plan', fn ($query) => $query->where('agents_included', true))
            ->chunkById(100, function ($users) use ($service, &$accounts): void {
                foreach ($users as $user) {
                    try {
                        $accounts += $service->runFor($user) ? 1 : 0;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info("{$agent}: did something for {$accounts} account(s).");

        return self::SUCCESS;
    }
}
