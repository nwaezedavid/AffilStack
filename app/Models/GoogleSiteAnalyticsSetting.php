<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Single-row (id=1) admin configuration for the "Connect with Google" site
 * analytics feature: one OAuth grant (reusing GoogleOauthSetting's client
 * id/secret, same pattern as GmailOAuthService/YouTubePublishingService)
 * requesting Analytics + Search Console + Tag Manager scopes together, then
 * auto-provisioning a GA4 property, a verified Search Console site, and a
 * GTM container — see GoogleSiteAnalyticsService for the actual API calls.
 *
 * credentials holds both the OAuth tokens (access_token/refresh_token/
 * expires_at) and every resource id this connection has provisioned
 * (ga_account, ga_property, ga_measurement_id, gsc_site_url,
 * gtm_account_id, gtm_container_id, gtm_public_id) — all encrypted at
 * rest, same shape as every other *Setting model in this app.
 */
#[Fillable(['is_enabled', 'credentials', 'connected_email', 'connected_at', 'verified_at', 'verification_status', 'verification_message'])]
class GoogleSiteAnalyticsSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'connected_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        // Not firstOrCreate(['id' => 1]): 'id' isn't fillable, so the INSERT silently
        // used the next auto-increment value instead — and on MariaDB/MySQL that
        // isn't 1 once any insert has been rolled back — so every call after
        // that created a fresh empty row and saved settings looked lost.
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1] + ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentialsArray(): array
    {
        return $this->credentials ?? [];
    }

    public function isConnected(): bool
    {
        return filled($this->credential('refresh_token'));
    }

    public function hasGa4(): bool
    {
        return filled($this->credential('ga_measurement_id'));
    }

    public function hasSearchConsole(): bool
    {
        return filled($this->credential('gsc_site_url')) && filled($this->credential('gsc_verified_at'));
    }

    public function hasTagManager(): bool
    {
        return filled($this->credential('gtm_public_id'));
    }

    /**
     * Audit item #7 (caching/performance) — this is read from the marketing
     * AND dashboard layouts' <head> on every single page view sitewide, so
     * (unlike current()'s admin-only callers) it must never cost a query.
     * Cached the same way SiteSetting::get() is, busted from booted() below.
     */
    public static function cachedGtmPublicId(): ?string
    {
        return Cache::rememberForever(
            'google_site_analytics:gtm_public_id',
            fn () => static::current()->credential('gtm_public_id')
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('google_site_analytics:gtm_public_id'));
        static::deleted(fn () => Cache::forget('google_site_analytics:gtm_public_id'));
    }
}
