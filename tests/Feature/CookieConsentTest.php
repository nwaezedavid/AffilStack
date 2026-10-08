<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\GoogleSiteAnalyticsSetting;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Agent 04: cookie consent — the banner, consent-gated third-party tags,
 * Google Consent Mode v2 defaults, the consent log, and the places the
 * banner must never appear.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function configureAllTags(): void
    {
        SiteSetting::set('seo_meta_pixel_id', '123456789012345');
        SiteSetting::set('seo_tiktok_pixel_id', 'CXXXXXXXXXXXXXXXXXXX');
        GoogleSiteAnalyticsSetting::current()->update(['credentials' => ['gtm_public_id' => 'GTM-ABC123']]);
    }

    /**
     * Every <script> block in the page, with its opening tag and body.
     *
     * @return array<int, array{tag: string, body: string}>
     */
    protected function scripts(string $html): array
    {
        preg_match_all('#(<script\b[^>]*>)(.*?)</script>#si', $html, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => ['tag' => $m[1], 'body' => $m[2]], $matches);
    }

    public function test_the_banner_and_preferences_render_hidden_on_marketing_pages(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="afs-consent-banner" hidden', $html);
        $this->assertStringContainsString('id="afs-consent-modal" hidden', $html);
        $this->assertStringContainsString('data-afs-action="accept"', $html);
        $this->assertStringContainsString('data-afs-action="reject"', $html);
        $this->assertStringContainsString('data-afs-action="customise"', $html);
        $this->assertStringContainsString('js/afs-consent.js', $html);
        $this->assertStringContainsString('#cookie-settings', $html, 'Footer "Cookie settings" link');
    }

    public function test_the_banner_is_on_the_dashboard_and_login_pages_too(): void
    {
        $this->get('/login')->assertOk()->assertSee('afs-consent-banner', false)->assertSee('data-cookie-settings', false);

        $user = User::factory()->create();
        $html = $this->actingAs($user)->get('/profile')->getContent();
        $this->assertStringContainsString('afs-consent-banner', $html);
    }

    public function test_no_tracking_script_executes_before_consent(): void
    {
        $this->configureAllTags();

        $html = $this->get('/')->assertOk()->getContent();

        foreach ($this->scripts($html) as $script) {
            $isInert = str_contains($script['tag'], 'type="text/plain"');

            foreach (['fbevents.js', 'analytics.tiktok.com', 'gtm.js?id=', 'gtag/js'] as $loader) {
                if (str_contains($script['body'], $loader)) {
                    $this->assertTrue($isInert, "{$loader} must sit in an inert text/plain script until consent");
                }
            }
        }

        $this->assertMatchesRegularExpression('#<script type="text/plain" data-afs-consent="marketing">\s*!function\(f,b,e,v,n,t,s\)#', $html);
        $this->assertStringContainsString('data-afs-consent="google"', $html);
        $this->assertStringNotContainsString('facebook.com/tr?id=', $html, 'No noscript pixel fallback');
        $this->assertStringNotContainsString('googletagmanager.com/ns.html', $html, 'No noscript GTM iframe');
    }

    public function test_google_consent_mode_v2_defaults_to_denied(): void
    {
        $this->configureAllTags();

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString("gtag('consent', 'default'", $html);
        foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'] as $signal) {
            $this->assertMatchesRegularExpression("#{$signal}: 'denied'#", $html);
        }

        $defaultsAt = strpos($html, "gtag('consent', 'default'");
        $gtmAt = strpos($html, 'gtm.js?id=');
        $this->assertLessThan($gtmAt, $defaultsAt, 'Consent defaults must be set before Tag Manager');
    }

    public function test_advanced_google_mode_loads_google_tags_immediately_but_pixels_stay_gated(): void
    {
        $this->configureAllTags();
        SiteSetting::set('consent_google_mode', 'advanced');

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<script type="text/javascript" data-afs-consent="google">', $html);
        $this->assertStringContainsString('<script type="text/plain" data-afs-consent="marketing">', $html);
    }

    public function test_bumping_the_policy_version_is_sent_to_the_browser(): void
    {
        SiteSetting::set('consent_policy_version', '2027-01');

        $this->get('/')->assertSee('"version":"2027-01"', false);
    }

    public function test_embed_routes_never_get_the_banner_or_cookies(): void
    {
        $response = $this->get('/embed/banner/300x250');

        $response->assertOk();
        $this->assertStringNotContainsString('afs-consent', $response->getContent());
        $this->assertStringNotContainsString('afs-analytics', $response->getContent());
        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_a_consent_choice_is_logged_with_a_hashed_ip_and_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $consentId = (string) Str::uuid();

        $this->actingAs($user)->postJson('/internal/consent', [
            'consent_id' => $consentId,
            'analytics' => true,
            'marketing' => false,
            'action' => 'custom',
            'source' => 'preferences',
            'policy_version' => '2026-10',
            'path' => '/pricing',
        ], ['Sec-GPC' => '1'])->assertOk();

        $record = ConsentRecord::sole();
        $this->assertSame($consentId, $record->consent_id);
        $this->assertTrue($record->analytics);
        $this->assertFalse($record->marketing);
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame('pricing', $record->page_path);
        $this->assertTrue($record->gpc);
        $this->assertSame(64, strlen((string) $record->ip_hash));
        $this->assertNotSame('127.0.0.1', $record->ip_hash);
    }

    public function test_cross_site_consent_posts_are_refused(): void
    {
        $this->postJson('/internal/consent', [
            'consent_id' => (string) Str::uuid(), 'analytics' => true, 'marketing' => true,
            'action' => 'accept_all', 'source' => 'banner', 'policy_version' => '2026-10',
        ], ['Origin' => 'https://evil.example'])->assertForbidden();

        $this->assertDatabaseCount('consent_records', 0);
    }

    public function test_the_consent_endpoint_is_rate_limited(): void
    {
        $payload = ['analytics' => false, 'marketing' => false, 'action' => 'reject_all', 'source' => 'banner', 'policy_version' => '2026-10'];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/internal/consent', $payload + ['consent_id' => (string) Str::uuid()])->assertOk();
        }

        $this->postJson('/internal/consent', $payload + ['consent_id' => (string) Str::uuid()])->assertStatus(429);
    }

    public function test_the_cookie_policy_lists_the_cookies_actually_in_use(): void
    {
        $this->seed(SitePagesSeeder::class);
        SiteSetting::set('seo_meta_pixel_id', '123456789012345');

        $this->get('/cookie-policy')
            ->assertOk()
            ->assertSee('Cookies we use right now')
            ->assertSee('afs_consent')
            ->assertSee('afs_vid')
            ->assertSee('_fbp, _fbc')
            ->assertDontSee('_ttp')
            ->assertSee('Global Privacy Control');
    }
}
