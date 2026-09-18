<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Single-row (id=1) admin configuration for Bing Webmaster Tools — see
 * Filament: Site > Bing Webmaster and App\Services\Analytics\BingWebmasterClient.
 *
 * Unlike Google (a full OAuth auto-connect, see GoogleSiteAnalyticsSetting),
 * Bing's own AddSite/verification APIs are unreliable when called
 * unattended (no OAuth, and third-party reports of AddSite rejecting
 * programmatic calls) — so this stays a guided flow: paste the API key from
 * bing.com/webmasters, add+choose "meta tag" verification there once, then
 * paste the resulting msvalidate.01 code back here so this app injects it
 * automatically. Once verified, sitemap submission is fully automatic
 * (Bing's SubmitSitemap endpoint is reliable and well documented).
 */
#[Fillable(['is_enabled', 'credentials', 'verification_code', 'verified_at', 'verification_status', 'verification_message'])]
class BingWebmasterSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function isConfigured(): bool
    {
        return filled($this->credential('api_key'));
    }

    /**
     * Audit item #7 (caching/performance) — this is read from the marketing
     * layout's <head> on every single public page view sitewide, so (unlike
     * current()'s admin-only callers) it must never cost a query. Cached the
     * same way SiteSetting::get() is, busted from booted() below.
     */
    public static function cachedVerificationCode(): ?string
    {
        return Cache::rememberForever(
            'bing_webmaster:verification_code',
            fn () => static::current()->verification_code
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('bing_webmaster:verification_code'));
        static::deleted(fn () => Cache::forget('bing_webmaster:verification_code'));
    }
}
