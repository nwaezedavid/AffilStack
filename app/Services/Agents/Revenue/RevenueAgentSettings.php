<?php

namespace App\Services\Agents\Revenue;

use App\Models\SiteSetting;

/**
 * Admin switches for the agents that work for AffilStack itself (Filament:
 * AI Agents > Revenue agents), stored as site settings.
 */
class RevenueAgentSettings
{
    public static function churnEnabled(): bool
    {
        return (bool) SiteSetting::get('agents_churn_saver_enabled', false);
    }

    public static function churnInactiveDays(): int
    {
        return max(3, (int) SiteSetting::get('agents_churn_saver_inactive_days', 10));
    }

    public static function churnOffer(): ?string
    {
        $offer = trim((string) SiteSetting::get('agents_churn_saver_offer', ''));

        return $offer !== '' ? $offer : null;
    }

    public static function nudgeEnabled(): bool
    {
        return (bool) SiteSetting::get('agents_upgrade_nudge_enabled', false);
    }

    public static function nudgeThreshold(): int
    {
        return min(100, max(50, (int) SiteSetting::get('agents_upgrade_nudge_threshold', 80)));
    }
}
