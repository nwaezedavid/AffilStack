<?php

namespace App\Services\Analytics;

use App\Models\GoogleOauthSetting;
use App\Models\GoogleSiteAnalyticsSetting;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The admin-facing "Connect with Google" auto-connect feature (Google Site
 * Kit-equivalent, per the user's explicit request): one OAuth grant against
 * the SAME client id/secret GoogleOauthSetting already holds (identical
 * reasoning to GmailOAuthService/YouTubePublishingService — this is a third
 * scope-expansion of the one Google OAuth app, not a new Google Cloud
 * project), then auto-provisioning a GA4 property, a verified Search
 * Console site, and a GTM container. Raw Http calls only, no google/apiclient
 * dependency (CLAUDE.md: no new dependencies without approval) — same as
 * every other Google integration in this app.
 *
 * Deliberately exposes provisioning as separate, individually re-runnable
 * steps (setUpAnalytics/verifySearchConsole/setUpTagManager) rather than one
 * all-or-nothing method: Search Console verification can only ever succeed
 * once this site is actually publicly reachable (Google has to fetch the
 * live page to see the verification meta tag), which won't be true until
 * after this app is deployed — so a step failing here is an expected,
 * recoverable state, not a bug, and each step must be safely repeatable
 * once its prerequisite becomes true.
 *
 * One Google-side limit this can't work around (Site Kit hits the exact
 * same wall): a brand-new Google account with NO Analytics account and NO
 * Tag Manager account ever created has nothing for the API to create a
 * property/container under — accounts.create doesn't exist for either
 * product. That one-time, ~30-second manual step (visit
 * analytics.google.com or tagmanager.google.com and accept the ToS to get
 * an account) is unavoidable; everything after it is fully automatic.
 */
class GoogleSiteAnalyticsService
{
    protected const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    protected const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';

    protected const GA_ADMIN_ENDPOINT = 'https://analyticsadmin.googleapis.com/v1beta';

    protected const SITE_VERIFICATION_ENDPOINT = 'https://www.googleapis.com/siteVerification/v1';

    protected const SEARCH_CONSOLE_ENDPOINT = 'https://searchconsole.googleapis.com/webmasters/v3';

    protected const TAG_MANAGER_ENDPOINT = 'https://www.googleapis.com/tagmanager/v2';

    protected const SCOPE = 'openid email '.
        'https://www.googleapis.com/auth/analytics.edit '.
        'https://www.googleapis.com/auth/webmasters '.
        'https://www.googleapis.com/auth/tagmanager.edit.containers '.
        'https://www.googleapis.com/auth/tagmanager.publish';

    protected GoogleOauthSetting $oauthSettings;

    public function __construct()
    {
        $this->oauthSettings = GoogleOauthSetting::current();
    }

    public function isAvailable(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    protected function clientId(): string
    {
        return (string) $this->oauthSettings->credential('client_id');
    }

    protected function clientSecret(): string
    {
        return (string) $this->oauthSettings->credential('client_secret');
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            // Forces a refresh_token back even on a reconnect — without
            // this, someone reconnecting after a revoke silently gets no
            // refresh_token and every provisioning step breaks the moment
            // the short-lived access token expires.
            'prompt' => 'consent',
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    public function handleCallback(string $code, string $redirectUri): void
    {
        $token = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            throw new RuntimeException('Google token exchange failed: '.$token->body());
        }

        if (! $token->json('refresh_token')) {
            throw new RuntimeException('Google did not grant offline access — please try connecting again and approve every permission requested.');
        }

        $profile = Http::withToken($token->json('access_token'))->get(self::USERINFO_ENDPOINT);

        $settings = GoogleSiteAnalyticsSetting::current();
        $settings->update([
            'is_enabled' => true,
            'connected_email' => (string) $profile->json('email'),
            'connected_at' => now(),
            'credentials' => [
                ...$settings->credentialsArray(),
                'access_token' => (string) $token->json('access_token'),
                'refresh_token' => (string) $token->json('refresh_token'),
                'expires_at' => now()->addSeconds((int) $token->json('expires_in', 3600))->toIso8601String(),
            ],
        ]);
    }

    /**
     * Refreshes and persists a new access token when the current one is
     * missing or expired — same lazy pattern as GmailOAuthService.
     */
    protected function freshAccessToken(GoogleSiteAnalyticsSetting $settings): string
    {
        $expiresAt = $settings->credential('expires_at');

        if ($expiresAt && now()->lt($expiresAt) && $settings->credential('access_token')) {
            return (string) $settings->credential('access_token');
        }

        if (! $settings->credential('refresh_token')) {
            throw new RuntimeException('Not connected to Google yet — click "Connect with Google" first.');
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $settings->credential('refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Could not refresh the Google connection — it may have been revoked. Please reconnect.');
        }

        $accessToken = (string) $response->json('access_token');

        $settings->update([
            'credentials' => [
                ...$settings->credentialsArray(),
                'access_token' => $accessToken,
                'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String(),
            ],
        ]);

        return $accessToken;
    }

    /**
     * @return array<int, array{name: string, displayName: string}>
     */
    public function listAnalyticsAccounts(): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();
        $token = $this->freshAccessToken($settings);

        $response = Http::withToken($token)->get(self::GA_ADMIN_ENDPOINT.'/accounts');

        if ($response->failed()) {
            throw new RuntimeException('Could not list Google Analytics accounts: '.$response->body());
        }

        return collect($response->json('accounts', []))
            ->map(fn (array $account) => ['name' => $account['name'], 'displayName' => $account['displayName']])
            ->all();
    }

    /**
     * Auto-creates a GA4 property + web data stream under the given
     * Analytics account and stores the resulting measurement ID. This is
     * the step that has nothing to fall back to if $accountName's account
     * doesn't exist yet (see class docblock) — listAnalyticsAccounts()
     * returning empty means "visit analytics.google.com once first".
     *
     * @return array{success: bool, message: string}
     */
    public function setUpAnalytics(string $accountName): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();
        $token = $this->freshAccessToken($settings);
        $siteUrl = rtrim(url('/'), '/').'/';
        $displayName = (string) SiteSetting::get('site_name', 'AffilStack');

        $property = Http::withToken($token)->post(self::GA_ADMIN_ENDPOINT.'/properties', [
            'parent' => $accountName,
            'displayName' => $displayName,
            'timeZone' => 'Etc/UTC',
            'currencyCode' => 'USD',
        ]);

        if ($property->failed() || ! $property->json('name')) {
            return ['success' => false, 'message' => 'Could not create a GA4 property: '.$property->body()];
        }

        $propertyName = (string) $property->json('name');

        $stream = Http::withToken($token)->post(self::GA_ADMIN_ENDPOINT."/{$propertyName}/dataStreams", [
            'type' => 'WEB_DATA_STREAM',
            'displayName' => $displayName.' — Web',
            'webStreamData' => ['defaultUri' => $siteUrl],
        ]);

        $measurementId = $stream->json('webStreamData.measurementId');

        if ($stream->failed() || ! $measurementId) {
            return ['success' => false, 'message' => 'Property created, but the web data stream failed: '.$stream->body()];
        }

        $settings->update([
            'credentials' => [
                ...$settings->credentialsArray(),
                'ga_account' => $accountName,
                'ga_property' => $propertyName,
                'ga_measurement_id' => $measurementId,
            ],
        ]);

        return ['success' => true, 'message' => "GA4 property created — measurement ID {$measurementId}."];
    }

    /**
     * Site Verification (META method) + adding the property to Search
     * Console + submitting the sitemap, in one call, all via the Site
     * Verification/Search Console APIs — no manual copy-pasting of a
     * verification code required. Steps 2+ can only succeed once this site
     * is actually publicly reachable, since Google fetches the live page to
     * confirm the tag — a failure here while developing locally/in a
     * sandbox is expected; re-running this after deployment is the fix.
     *
     * @return array{success: bool, message: string}
     */
    public function verifySearchConsole(): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();
        $token = $this->freshAccessToken($settings);
        $siteUrl = rtrim(url('/'), '/').'/';

        $tokenResponse = Http::withToken($token)->post(self::SITE_VERIFICATION_ENDPOINT.'/token', [
            'verificationMethod' => 'META',
            'site' => ['type' => 'SITE', 'identifier' => $siteUrl],
        ]);

        $verificationToken = $tokenResponse->json('token');

        if ($tokenResponse->failed() || ! $verificationToken) {
            return ['success' => false, 'message' => 'Could not get a verification token from Google: '.$tokenResponse->body()];
        }

        // Renders immediately via the existing <meta name="google-site-
        // verification"> tag in layouts/marketing.blade.php — same field
        // the manual-entry SEO settings page already exposes, so this and
        // the old manual flow can never disagree.
        SiteSetting::set('seo_gsc_verification', $verificationToken);

        $insert = Http::withToken($token)->post(self::SITE_VERIFICATION_ENDPOINT.'/webResource?verificationMethod=META', [
            'site' => ['type' => 'SITE', 'identifier' => $siteUrl],
        ]);

        if ($insert->failed()) {
            return [
                'success' => false,
                'message' => "The verification tag is saved and live on your homepage, but Google couldn't confirm it yet — this usually means the site isn't publicly reachable at {$siteUrl} right now. Once it's deployed and live, click this again.",
            ];
        }

        $addSite = Http::withToken($token)->put(self::SEARCH_CONSOLE_ENDPOINT.'/sites/'.rawurlencode($siteUrl));

        if ($addSite->failed() && $addSite->status() !== 409) {
            // 409 = already added, which is fine — anything else is a real failure.
            return ['success' => false, 'message' => 'Verified, but adding the property to Search Console failed: '.$addSite->body()];
        }

        $sitemapUrl = rtrim(url('/'), '/').'/sitemap.xml';
        Http::withToken($token)->put(
            self::SEARCH_CONSOLE_ENDPOINT.'/sites/'.rawurlencode($siteUrl).'/sitemaps/'.rawurlencode($sitemapUrl)
        );

        $settings->update([
            'credentials' => [...$settings->credentialsArray(), 'gsc_site_url' => $siteUrl, 'gsc_verified_at' => now()->toIso8601String()],
        ]);

        return ['success' => true, 'message' => "Search Console verified for {$siteUrl} and the sitemap was submitted."];
    }

    /**
     * @return array<int, array{accountId: string, name: string}>
     */
    public function listTagManagerAccounts(): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();
        $token = $this->freshAccessToken($settings);

        $response = Http::withToken($token)->get(self::TAG_MANAGER_ENDPOINT.'/accounts');

        if ($response->failed()) {
            throw new RuntimeException('Could not list Tag Manager accounts: '.$response->body());
        }

        return collect($response->json('account', []))
            ->map(fn (array $account) => ['accountId' => $account['accountId'], 'name' => $account['name']])
            ->all();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function setUpTagManager(string $accountId): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();
        $token = $this->freshAccessToken($settings);
        $displayName = (string) SiteSetting::get('site_name', 'AffilStack');

        $container = Http::withToken($token)->post(self::TAG_MANAGER_ENDPOINT."/accounts/{$accountId}/containers", [
            'name' => $displayName,
            'usageContext' => ['web'],
        ]);

        $publicId = $container->json('publicId');

        if ($container->failed() || ! $publicId) {
            return ['success' => false, 'message' => 'Could not create a GTM container: '.$container->body()];
        }

        $settings->update([
            'credentials' => [
                ...$settings->credentialsArray(),
                'gtm_account_id' => $accountId,
                'gtm_container_id' => $container->json('containerId'),
                'gtm_public_id' => $publicId,
            ],
        ]);

        return ['success' => true, 'message' => "Tag Manager container created — {$publicId}."];
    }

    public function disconnect(): void
    {
        GoogleSiteAnalyticsSetting::current()->update([
            'is_enabled' => false,
            'credentials' => [],
            'connected_email' => null,
            'connected_at' => null,
        ]);
    }
}
