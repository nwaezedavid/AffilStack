<?php

namespace App\Support\Lifecycle;

use App\Models\SiteSetting;

/**
 * Admin-tunable knobs for lifecycle emails and the activity status shown
 * in the admin Users list. Stored in SiteSetting (edited from Support >
 * Lifecycle Emails > Settings); config/lifecycle.php supplies defaults.
 */
class LifecycleSettings
{
    public static function sendHour(): int
    {
        return min(23, max(0, (int) SiteSetting::get('lifecycle_send_hour', config('lifecycle.send_hour', 9))));
    }

    public static function activeWithinDays(): int
    {
        return max(1, (int) SiteSetting::get('lifecycle_active_within_days', config('lifecycle.active_within_days', 7)));
    }

    public static function inactiveAfterDays(): int
    {
        return max(self::activeWithinDays() + 1, (int) SiteSetting::get('lifecycle_inactive_after_days', config('lifecycle.inactive_after_days', 30)));
    }

    /**
     * Master switch per campaign type, on top of each email's own toggle.
     */
    public static function campaignEnabled(string $type): bool
    {
        return SiteSetting::flag("lifecycle_{$type}_enabled", true);
    }

    /**
     * @param  array{send_hour?: int, active_within_days?: int, inactive_after_days?: int, birthday_enabled?: bool, festive_enabled?: bool, reengagement_enabled?: bool}  $values
     */
    public static function save(array $values): void
    {
        foreach (['send_hour', 'active_within_days', 'inactive_after_days'] as $key) {
            if (array_key_exists($key, $values)) {
                SiteSetting::set("lifecycle_{$key}", (string) (int) $values[$key]);
            }
        }

        foreach (['birthday', 'festive', 'reengagement'] as $type) {
            if (array_key_exists("{$type}_enabled", $values)) {
                SiteSetting::setFlag("lifecycle_{$type}_enabled", (bool) $values["{$type}_enabled"]);
            }
        }
    }
}
