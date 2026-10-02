<?php

namespace App\Console\Commands\Analytics;

use App\Services\Analytics\AnalyticsProfileBuilder;
use App\Services\Analytics\SegmentEvaluator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Agent 04: refreshes the interest & intent profiles behind Audience
 * Insights and the advertising segments. Incremental by default (people
 * active since the previous run, with a 5-minute overlap); --full rebuilds
 * everyone, which the nightly run does so time-based stages (a customer
 * drifting into "at risk") and the 30-day engagement score stay current.
 */
#[Signature('analytics:build-profiles {--full : Rebuild every profile, not just recently active people}')]
#[Description('Rebuild analytics interest & intent profiles (lifecycle stage, engagement score, interests)')]
class BuildAnalyticsProfiles extends Command
{
    public const LAST_RUN_KEY = 'analytics:profiles:last_run';

    public function handle(AnalyticsProfileBuilder $builder): int
    {
        SegmentEvaluator::ensurePresets();

        $startedAt = now();
        $lastRun = Cache::get(self::LAST_RUN_KEY);
        $since = ! $this->option('full') && $lastRun ? Carbon::parse($lastRun)->subMinutes(5) : null;

        $counts = $builder->rebuild($since);

        Cache::forever(self::LAST_RUN_KEY, $startedAt->toIso8601String());

        $this->info(sprintf('Profiles refreshed: %d users, %d anonymous visitors; %d removed (consent withdrawn).', $counts['users'], $counts['visitors'], $counts['removed']));

        return self::SUCCESS;
    }
}
