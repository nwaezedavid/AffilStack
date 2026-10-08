<?php

namespace App\Support\Analytics;

use App\Models\SiteSetting;

/**
 * Agent 04: the admin-editable cookie-consent settings (Analytics → Cookie
 * Consent), each with a safe default so the banner works before anyone
 * opens that page. Consent is always required for analytics and
 * advertising cookies — there is deliberately no setting that loads them
 * without it; switching the banner off simply means they never load.
 */
class ConsentSettings
{
    public const DEFAULT_TITLE = 'We value your privacy';

    public const DEFAULT_TEXT = 'We use essential cookies to make this site work. With your permission we\'d also like to use analytics cookies to understand how our pages are used, and advertising cookies to measure and personalise our ads. You can accept, reject, or choose — and change your mind any time from "Cookie settings".';

    public static function bannerEnabled(): bool
    {
        return SiteSetting::flag('consent_banner_enabled', true);
    }

    public static function title(): string
    {
        return (string) (SiteSetting::get('consent_banner_title') ?: self::DEFAULT_TITLE);
    }

    public static function text(): string
    {
        return (string) (SiteSetting::get('consent_banner_text') ?: self::DEFAULT_TEXT);
    }

    public static function policyUrl(): string
    {
        $url = (string) SiteSetting::get('consent_policy_url');

        return $url !== '' ? $url : route('cookie-policy');
    }

    public static function policyVersion(): string
    {
        $version = (string) SiteSetting::get('consent_policy_version');

        return self::isValidVersion($version) ? $version : (string) config('analytics.policy_version');
    }

    public static function isValidVersion(?string $version): bool
    {
        return is_string($version) && preg_match('/^[A-Za-z0-9._-]{1,20}$/', $version) === 1;
    }

    /**
     * Whether a page view from someone who hasn't allowed analytics may
     * still be counted as an anonymous total (no cookie, no id, no user).
     */
    public static function countsAnonymousViews(): bool
    {
        return SiteSetting::flag('consent_count_anonymous_views', true);
    }

    /**
     * "basic": Google tags (GTM/GA4) load only after consent. "advanced":
     * they load immediately with every Consent Mode signal denied, so
     * Google receives cookieless pings — more modelled data, but some EU
     * regulators consider it non-compliant, hence opt-in.
     */
    public static function googleMode(): string
    {
        return SiteSetting::get('consent_google_mode') === 'advanced' ? 'advanced' : 'basic';
    }

    public static function retentionMonths(): int
    {
        $months = (int) SiteSetting::get('analytics_retention_months', config('analytics.retention_months'));

        return max(1, min(60, $months ?: (int) config('analytics.retention_months')));
    }
}
