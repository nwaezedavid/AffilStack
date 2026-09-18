<?php

namespace App\Services\Settings;

use App\Models\BingWebmasterSetting;
use App\Models\BrainAgentSetting;
use App\Models\GoogleOauthSetting;
use App\Models\GoogleSiteAnalyticsSetting;
use App\Models\HeyGenSetting;
use App\Models\InstagramSetting;
use App\Models\LinkedInOauthSetting;
use App\Models\PartnerStackSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\SiteSetting;
use App\Models\TikTokSetting;
use App\Services\AI\AnthropicClient;
use App\Services\Analytics\BingWebmasterClient;
use App\Services\Analytics\GoogleSiteAnalyticsService;
use App\Services\Auth\GoogleOAuthService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Referrals\PartnerStackClient;
use App\Services\Video\HeyGenClient;

/**
 * The "active AI Agent... verifying every connection" requirement (audit
 * item #6) — a single, admin-wide inventory of every external integration
 * this app's settings pages configure, each one's current status, and
 * whether it's actually reachable by users on the frontend right now.
 * Powers the Filament "Connections Health" page.
 *
 * Deliberately doesn't duplicate any gateway/client's own verifyCredentials()
 * logic — every live check here just calls that same method the
 * integration's own settings page already uses, so status never disagrees
 * between the two screens. Where an integration has no way to be verified
 * outside a real browser OAuth flow (LinkedIn/TikTok/Instagram — see class
 * docblocks on those Filament pages), this only reports whether it's
 * configured and points the admin at the exact frontend action to
 * click-test instead of pretending to have checked it.
 */
class SettingsHealthChecker
{
    protected const PAYMENT_GATEWAYS = ['flutterwave', 'stripe', 'paystack', 'paypal'];

    /**
     * null = never checked, true/false = the last check's actual result —
     * distinct states the health page renders as three different badges,
     * so "never checked" is never confused with "checked and failed".
     */
    protected function tristate(?string $status): ?bool
    {
        return $status === null ? null : $status === 'success';
    }

    /**
     * Fast, no-network snapshot — safe to call on every page load.
     * $googleOverride carries the current page view's freshly-run Google
     * result, when there is one (see runLiveChecks()) — Google is the only
     * item with nowhere of its own to persist a status column (see
     * GoogleOAuthSettings), so it can't be read back from the database like
     * every other item here.
     *
     * @param  array{success: bool, message: string}|null  $googleOverride
     * @return array<int, array<string, mixed>>
     */
    public function items(?array $googleOverride = null): array
    {
        return [
            ...array_map(fn (string $key) => $this->paymentGatewayItem($key), self::PAYMENT_GATEWAYS),
            $this->partnerStackItem(),
            $this->googleItem($googleOverride),
            $this->linkedInItem(),
            $this->tikTokItem(),
            $this->instagramItem(),
            $this->heyGenItem(),
            $this->anthropicItem(),
            $this->metaMcpItem(),
            $this->googleAnalyticsItem(),
            $this->searchConsoleItem(),
            $this->tagManagerItem(),
            $this->bingItem(),
            $this->metaPixelItem(),
            $this->tiktokPixelItem(),
        ];
    }

    /**
     * Re-runs every live-checkable item's own verifyCredentials()-equivalent
     * and persists the result to that item's own settings row — the exact
     * same write its own settings page's "Verify" button already makes, so
     * this never becomes a second source of truth. Called only from an
     * explicit admin action, never on page load — these are real API calls.
     *
     * @return array{success: bool, message: string} the Google result — the
     *                                               one item with nothing
     *                                               of its own to persist
     *                                               it to, so the caller
     *                                               must hand it back into
     *                                               items() itself
     */
    public function runLiveChecks(): array
    {
        foreach (self::PAYMENT_GATEWAYS as $key) {
            $gateway = app(PaymentGatewayManager::class)->get($key);
            $result = $gateway->verifyCredentials();

            PaymentGatewaySetting::forGateway($key)->update([
                'last_verified_at' => now(),
                'last_verification_status' => $result['success'] ? 'success' : 'failed',
                'last_verification_message' => $result['message'],
            ]);
        }

        $partnerStack = PartnerStackSetting::current();
        $result = (new PartnerStackClient(
            (string) $partnerStack->credential('public_key'),
            (string) $partnerStack->credential('secret_key'),
        ))->verifyCredentials();
        $partnerStack->update([
            'verified_at' => now(),
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_message' => $result['message'],
        ]);

        $heygen = HeyGenSetting::current();
        $result = (new HeyGenClient((string) $heygen->credential('api_key')))->verifyApiKey();
        $heygen->update([
            'verified_at' => now(),
            'verification_status' => $result['success'] ? 'success' : 'failed',
            'verification_message' => $result['message'],
        ]);

        $brain = BrainAgentSetting::current();
        $anthropicResult = (new AnthropicClient(
            apiKey: (string) $brain->credential('anthropic_api_key'),
            model: (string) ($brain->credential('anthropic_model') ?: 'claude-sonnet-5'),
        ))->verifyApiKey();
        $brain->update([
            'anthropic_verified_at' => now(),
            'anthropic_verification_status' => $anthropicResult['success'] ? 'success' : 'failed',
            'anthropic_verification_message' => $anthropicResult['message'],
        ]);

        if ($brain->credential('meta_mcp_url')) {
            $mcpResult = (new AnthropicClient(
                apiKey: (string) $brain->credential('anthropic_api_key'),
                model: (string) ($brain->credential('anthropic_model') ?: 'claude-sonnet-5'),
            ))->verifyMcpConnection((string) $brain->credential('meta_mcp_url'), $brain->credential('meta_mcp_token'));
            $brain->update([
                'meta_mcp_verified_at' => now(),
                'meta_mcp_verification_status' => $mcpResult['success'] ? 'success' : 'failed',
                'meta_mcp_verification_message' => $mcpResult['message'],
            ]);
        }

        $bing = BingWebmasterSetting::current();
        $bingResult = (new BingWebmasterClient((string) $bing->credential('api_key')))->verifySite(rtrim(url('/'), '/'));
        $bing->update([
            'verified_at' => now(),
            'verification_status' => $bingResult['success'] ? 'success' : 'failed',
            'verification_message' => $bingResult['message'],
        ]);

        $siteAnalytics = GoogleSiteAnalyticsSetting::current();
        if ($siteAnalytics->isConnected()) {
            $gscResult = app(GoogleSiteAnalyticsService::class)->verifySearchConsole();
            $siteAnalytics->update([
                'verified_at' => now(),
                'verification_status' => $gscResult['success'] ? 'success' : 'failed',
                'verification_message' => $gscResult['message'],
            ]);
        }

        // Google has no persisted status column (see GoogleOAuthSettings) —
        // its live result is only ever shown for the current page view.
        return app(GoogleOAuthService::class)->verifyCredentials();
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentGatewayItem(string $key): array
    {
        $settings = PaymentGatewaySetting::forGateway($key);

        return [
            'key' => "payment_{$key}",
            'label' => ucfirst($key),
            'group' => 'Payments',
            'what_it_does' => 'Lets a customer pay for a plan.',
            'settings_url' => route('filament.admin.pages.payment-gateway-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('secret_key')),
            'checked_at' => $settings->last_verified_at,
            'success' => $this->tristate($settings->last_verification_status),
            'message' => $settings->last_verification_message,
            'live_checkable' => true,
            'frontend_hint' => 'Test by starting checkout with this gateway selected from the pricing or billing page.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function partnerStackItem(): array
    {
        $settings = PartnerStackSetting::current();

        return [
            'key' => 'partnerstack',
            'label' => 'PartnerStack',
            'group' => 'Referrals',
            'what_it_does' => 'Connection-only for now — no data syncs anywhere yet.',
            'settings_url' => route('filament.admin.pages.partner-stack-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('public_key')),
            'checked_at' => $settings->verified_at,
            'success' => $this->tristate($settings->verification_status),
            'message' => $settings->verification_message,
            'live_checkable' => true,
            'frontend_hint' => 'Nothing user-facing depends on this yet.',
        ];
    }

    /**
     * @param  array{success: bool, message: string}|null  $result
     * @return array<string, mixed>
     */
    protected function googleItem(?array $result): array
    {
        $settings = GoogleOauthSetting::current();

        return [
            'key' => 'google_oauth',
            'label' => 'Google Login',
            'group' => 'Sign-in & Social',
            'what_it_does' => '"Continue with Google" on sign-in/signup.',
            'settings_url' => route('filament.admin.pages.google-o-auth-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('client_id')),
            'checked_at' => $result ? now() : null,
            'success' => $result['success'] ?? null,
            'message' => $result['message'] ?? null,
            'live_checkable' => true,
            'frontend_hint' => 'Click "Continue with Google" on the sign-in page as a real visitor would.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function linkedInItem(): array
    {
        $settings = LinkedInOauthSetting::current();

        return [
            'key' => 'linkedin_oauth',
            'label' => 'LinkedIn Connect',
            'group' => 'Sign-in & Social',
            'what_it_does' => 'Verifies a user\'s identity for exported content.',
            'settings_url' => route('filament.admin.pages.linked-in-o-auth-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('client_id')),
            'checked_at' => null,
            'success' => null,
            'message' => "Can't be checked with a stored key alone — LinkedIn only confirms a client id/secret pair by completing a real OAuth login.",
            'live_checkable' => false,
            'frontend_hint' => 'Click "Connect LinkedIn" from a user\'s Social Connections page and confirm it completes without an error.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tikTokItem(): array
    {
        $settings = TikTokSetting::current();

        return [
            'key' => 'tiktok',
            'label' => 'TikTok Publishing',
            'group' => 'Sign-in & Social',
            'what_it_does' => 'Publishes a UGC video to a user\'s TikTok account.',
            'settings_url' => route('filament.admin.pages.tik-tok-publishing-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('client_key')),
            'checked_at' => null,
            'success' => $settings->approval_status === 'approved' ? true : null,
            'message' => match ($settings->approval_status) {
                'approved' => 'TikTok\'s Content Posting API audit is approved.',
                'pending' => 'Configured, but TikTok\'s audit is still pending — publishing falls back to "download and post manually" until approved.',
                default => 'Configured, but the Content Posting API audit hasn\'t been submitted yet.',
            },
            'live_checkable' => false,
            'frontend_hint' => 'Click "Connect TikTok" from a user\'s Social Connections page and confirm it completes without an error.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function instagramItem(): array
    {
        $settings = InstagramSetting::current();

        return [
            'key' => 'instagram',
            'label' => 'Instagram Publishing',
            'group' => 'Sign-in & Social',
            'what_it_does' => 'Publishes a UGC video as a Reel to a user\'s Instagram account.',
            'settings_url' => route('filament.admin.pages.instagram-publishing-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('app_id')),
            'checked_at' => null,
            'success' => $settings->approval_status === 'approved' ? true : null,
            'message' => match ($settings->approval_status) {
                'approved' => 'Meta\'s App Review for instagram_content_publish is approved.',
                'pending' => 'Configured, but Meta\'s App Review is still pending — publishing falls back to "download and post manually" until approved.',
                default => 'Configured, but App Review hasn\'t been submitted yet.',
            },
            'live_checkable' => false,
            'frontend_hint' => 'Click "Connect Instagram" from a user\'s Social Connections page and confirm it completes without an error.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function heyGenItem(): array
    {
        $settings = HeyGenSetting::current();

        return [
            'key' => 'heygen',
            'label' => 'HeyGen (UGC Video)',
            'group' => 'AI Features',
            'what_it_does' => 'Renders every user\'s "Generate video" click.',
            'settings_url' => route('filament.admin.pages.ugc-video-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('api_key')),
            'checked_at' => $settings->verified_at,
            'success' => $this->tristate($settings->verification_status),
            'message' => $settings->verification_message,
            'live_checkable' => true,
            'frontend_hint' => 'Test with a real "Generate video" click from a user account.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function anthropicItem(): array
    {
        $settings = BrainAgentSetting::current();

        return [
            'key' => 'anthropic',
            'label' => 'Claude (Brain agent)',
            'group' => 'AI Features',
            'what_it_does' => 'Drafts and (once enabled) launches/optimizes ad campaigns.',
            'settings_url' => route('filament.admin.pages.brain-agent-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('anthropic_api_key')),
            'checked_at' => $settings->anthropic_verified_at,
            'success' => $this->tristate($settings->anthropic_verification_status),
            'message' => $settings->anthropic_verification_message,
            'live_checkable' => true,
            'frontend_hint' => 'Open the Brain dashboard and draft a campaign.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metaMcpItem(): array
    {
        $settings = BrainAgentSetting::current();

        return [
            'key' => 'meta_mcp',
            'label' => 'Meta Ads MCP server',
            'group' => 'AI Features',
            'what_it_does' => 'Lets Brain actually launch/optimize Meta ad campaigns.',
            'settings_url' => route('filament.admin.pages.brain-agent-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => filled($settings->credential('meta_mcp_url')),
            'checked_at' => $settings->meta_mcp_verified_at,
            'success' => $this->tristate($settings->meta_mcp_verification_status),
            'message' => $settings->meta_mcp_verification_message,
            'live_checkable' => true,
            'frontend_hint' => 'Approve & launch a drafted campaign from the Brain dashboard.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function googleAnalyticsItem(): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();

        return [
            'key' => 'google_analytics',
            'label' => 'Google Analytics 4',
            'group' => 'Analytics & SEO',
            'what_it_does' => 'Tracks visitor behavior sitewide.',
            'settings_url' => route('filament.admin.pages.google-site-analytics-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => $settings->hasGa4(),
            'checked_at' => null,
            'success' => $settings->hasGa4() ? true : null,
            'message' => $settings->hasGa4()
                ? "Measurement ID {$settings->credential('ga_measurement_id')} — tracking is live."
                : 'Connect with Google, then create the GA4 property from Site > Site Analytics.',
            'live_checkable' => false,
            'frontend_hint' => 'View Realtime reports in Google Analytics while browsing the site yourself.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function searchConsoleItem(): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();

        return [
            'key' => 'search_console',
            'label' => 'Google Search Console',
            'group' => 'Analytics & SEO',
            'what_it_does' => 'Confirms Google indexes this site and submits the sitemap.',
            'settings_url' => route('filament.admin.pages.google-site-analytics-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => $settings->hasSearchConsole(),
            'checked_at' => $settings->verified_at,
            'success' => $this->tristate($settings->verification_status),
            'message' => $settings->verification_message,
            'live_checkable' => $settings->isConnected(),
            'frontend_hint' => 'Check the Coverage report in Search Console once the site has been live a few days.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tagManagerItem(): array
    {
        $settings = GoogleSiteAnalyticsSetting::current();

        return [
            'key' => 'tag_manager',
            'label' => 'Google Tag Manager',
            'group' => 'Analytics & SEO',
            'what_it_does' => 'Single container for every tracking tag sitewide (takes over from the bare GA4 snippet once set up).',
            'settings_url' => route('filament.admin.pages.google-site-analytics-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => $settings->hasTagManager(),
            'checked_at' => null,
            'success' => $settings->hasTagManager() ? true : null,
            'message' => $settings->hasTagManager()
                ? "Container {$settings->credential('gtm_public_id')} is injected sitewide."
                : 'Connect with Google, then create the container from Site > Site Analytics.',
            'live_checkable' => false,
            'frontend_hint' => 'Use Tag Manager\'s own Preview mode against the live site.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bingItem(): array
    {
        $settings = BingWebmasterSetting::current();

        return [
            'key' => 'bing_webmaster',
            'label' => 'Bing Webmaster Tools',
            'group' => 'Analytics & SEO',
            'what_it_does' => 'Confirms Bing indexes this site and submits the sitemap.',
            'settings_url' => route('filament.admin.pages.bing-webmaster-settings'),
            'is_enabled' => $settings->is_enabled,
            'is_configured' => $settings->isConfigured(),
            'checked_at' => $settings->verified_at,
            'success' => $this->tristate($settings->verification_status),
            'message' => $settings->verification_message,
            'live_checkable' => true,
            'frontend_hint' => 'Check the Site Explorer report in Bing Webmaster Tools once the site has been live a few days.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metaPixelItem(): array
    {
        $pixelId = SiteSetting::get('seo_meta_pixel_id');

        return [
            'key' => 'meta_pixel',
            'label' => 'Meta Pixel',
            'group' => 'Analytics & SEO',
            'what_it_does' => 'Tracks page views for Meta/Facebook/Instagram ad campaigns.',
            'settings_url' => route('filament.admin.pages.seo-settings'),
            'is_enabled' => filled($pixelId),
            'is_configured' => filled($pixelId),
            'checked_at' => null,
            'success' => null,
            'message' => filled($pixelId) ? "Pixel {$pixelId} is injected sitewide." : 'Not configured yet.',
            'live_checkable' => false,
            'frontend_hint' => 'Use Meta Events Manager\'s "Test events" tool against the live site.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function tiktokPixelItem(): array
    {
        $pixelId = SiteSetting::get('seo_tiktok_pixel_id');

        return [
            'key' => 'tiktok_pixel',
            'label' => 'TikTok Pixel',
            'group' => 'Analytics & SEO',
            'what_it_does' => 'Tracks page views for TikTok ad campaigns.',
            'settings_url' => route('filament.admin.pages.seo-settings'),
            'is_enabled' => filled($pixelId),
            'is_configured' => filled($pixelId),
            'checked_at' => null,
            'success' => null,
            'message' => filled($pixelId) ? "Pixel {$pixelId} is injected sitewide." : 'Not configured yet.',
            'live_checkable' => false,
            'frontend_hint' => 'Use TikTok Events Manager\'s diagnostics tool against the live site.',
        ];
    }
}
