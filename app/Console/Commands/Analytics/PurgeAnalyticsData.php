<?php

namespace App\Console\Commands\Analytics;

use App\Services\Analytics\AnalyticsDataEraser;
use App\Support\Analytics\ConsentSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Agent 04: storage limitation (NDPA 2023 s.24(1)(d), GDPR art. 5(1)(e)) —
 * deletes analytics events, sessions, visitors, profiles and consent
 * records older than the retention window set in Analytics → Cookie
 * Consent (default 13 months). Runs nightly.
 */
#[Signature('analytics:purge')]
#[Description('Delete analytics data older than the configured retention period')]
class PurgeAnalyticsData extends Command
{
    public function handle(AnalyticsDataEraser $eraser): int
    {
        $counts = $eraser->purgeExpired();

        $this->info('Retention '.ConsentSettings::retentionMonths().' months — removed: '.collect($counts)->map(fn ($n, $table) => "{$table} {$n}")->implode(', '));

        return self::SUCCESS;
    }
}
