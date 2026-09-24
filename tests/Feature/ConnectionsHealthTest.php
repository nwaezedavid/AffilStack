<?php

namespace Tests\Feature;

use App\Filament\Pages\ConnectionsHealth;
use App\Models\BingWebmasterSetting;
use App\Models\BrainAgentSetting;
use App\Models\GitHubSyncSetting;
use App\Models\GoogleOauthSetting;
use App\Models\GoogleSiteAnalyticsSetting;
use App\Models\HeyGenSetting;
use App\Models\InstagramSetting;
use App\Models\LinkedInOauthSetting;
use App\Models\PartnerStackSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\SiteSetting;
use App\Models\TikTokSetting;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Settings\SettingsHealthChecker;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * "Every admin setting should have an active AI Agent assisting and
 * verifying every connection" (audit item #6) — see SettingsHealthChecker
 * for what each item actually checks.
 */
class ConnectionsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        foreach (['flutterwave', 'stripe', 'paystack', 'paypal'] as $gateway) {
            PaymentGatewaySetting::create(['gateway' => $gateway, 'is_enabled' => false]);
        }
        PartnerStackSetting::current();
        GoogleOauthSetting::current();
        LinkedInOauthSetting::current();
        TikTokSetting::current();
        InstagramSetting::current();
        HeyGenSetting::current();
        BrainAgentSetting::current();
        BingWebmasterSetting::current();
        GoogleSiteAnalyticsSetting::current();
    }

    /**
     * Task: "enhance the Analytics and SEO... have my AI agent assist in
     * making sure each connection is successful" — the 6 new Analytics &
     * SEO items must show up here the same way every other integration
     * does, not as a second, disconnected status screen.
     */
    public function test_the_six_new_analytics_and_seo_items_appear_unconfigured_by_default(): void
    {
        $items = app(SettingsHealthChecker::class)->items();

        foreach (['google_analytics', 'search_console', 'tag_manager', 'bing_webmaster', 'meta_pixel', 'tiktok_pixel'] as $key) {
            $item = collect($items)->firstWhere('key', $key);
            $this->assertNotNull($item, "{$key} should be present in the health check inventory.");
            $this->assertSame('Analytics & SEO', $item['group']);
            $this->assertFalse($item['is_configured']);
        }
    }

    public function test_google_analytics_and_tag_manager_report_configured_once_provisioned(): void
    {
        GoogleSiteAnalyticsSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['ga_measurement_id' => 'G-ABC123', 'gtm_public_id' => 'GTM-XYZ'],
        ]);

        $items = app(SettingsHealthChecker::class)->items();

        $this->assertTrue(collect($items)->firstWhere('key', 'google_analytics')['is_configured']);
        $this->assertTrue(collect($items)->firstWhere('key', 'tag_manager')['is_configured']);
    }

    public function test_meta_and_tiktok_pixels_report_configured_once_a_pixel_id_is_saved(): void
    {
        SiteSetting::set('seo_meta_pixel_id', '123456789012345');
        SiteSetting::set('seo_tiktok_pixel_id', 'CXXXXXXXXXXXXXXXXXXX');

        $items = app(SettingsHealthChecker::class)->items();

        $this->assertTrue(collect($items)->firstWhere('key', 'meta_pixel')['is_configured']);
        $this->assertTrue(collect($items)->firstWhere('key', 'tiktok_pixel')['is_configured']);
    }

    public function test_running_live_checks_verifies_bing_and_persists_the_result(): void
    {
        BingWebmasterSetting::current()->update(['credentials' => ['api_key' => 'a-bing-key']]);
        Http::fake(['ssl.bing.com/*' => Http::response(['d' => []])]);

        app(SettingsHealthChecker::class)->runLiveChecks();

        $bing = BingWebmasterSetting::current();
        $this->assertSame('failed', $bing->verification_status);
        $this->assertNotNull($bing->verified_at);
    }

    public function test_running_live_checks_only_re_verifies_search_console_once_google_is_actually_connected(): void
    {
        Http::fake(fn () => Http::response([], 401));

        // Not connected yet — no Search Console call should be attempted,
        // and no verification status should be written.
        app(SettingsHealthChecker::class)->runLiveChecks();

        $this->assertNull(GoogleSiteAnalyticsSetting::current()->verification_status);
    }

    public function test_an_unconfigured_item_reports_not_configured_and_never_checked(): void
    {
        $items = app(SettingsHealthChecker::class)->items();
        $flutterwave = collect($items)->firstWhere('key', 'payment_flutterwave');

        $this->assertFalse($flutterwave['is_configured']);
        $this->assertNull($flutterwave['success']);
        $this->assertNull($flutterwave['checked_at']);
    }

    public function test_items_the_gateway_apis_cant_verify_are_marked_not_live_checkable(): void
    {
        $items = app(SettingsHealthChecker::class)->items();

        foreach (['linkedin_oauth', 'tiktok', 'instagram'] as $key) {
            $item = collect($items)->firstWhere('key', $key);
            $this->assertFalse($item['live_checkable'], "{$key} should not claim to be live-checkable.");
            $this->assertNotEmpty($item['frontend_hint']);
        }
    }

    public function test_running_live_checks_persists_the_result_to_each_items_own_settings_row(): void
    {
        PaymentGatewaySetting::forGateway('flutterwave')->update(['credentials' => ['secret_key' => 'flw_test_fake']]);
        PaymentGatewaySetting::forGateway('stripe')->update(['credentials' => ['secret_key' => 'sk_test_fake']]);
        PaymentGatewaySetting::forGateway('paystack')->update(['credentials' => ['secret_key' => 'sk_test_fake']]);
        PaymentGatewaySetting::forGateway('paypal')->update(['credentials' => ['client_id' => 'x', 'client_secret' => 'y']]);

        Http::fake(fn () => Http::response([], 401));

        app(SettingsHealthChecker::class)->runLiveChecks();

        $flutterwave = PaymentGatewaySetting::forGateway('flutterwave');
        $this->assertSame('failed', $flutterwave->last_verification_status);
        $this->assertNotNull($flutterwave->last_verified_at);

        // The very same row PaymentGatewaySettings' own "Verify" action
        // reads — this must never become a second source of truth.
        $items = app(SettingsHealthChecker::class)->items();
        $item = collect($items)->firstWhere('key', 'payment_flutterwave');
        $this->assertFalse($item['success']);
    }

    public function test_admin_can_load_the_page_and_explain_a_failed_connection_with_ai(): void
    {
        PaymentGatewaySetting::forGateway('flutterwave')->update([
            'last_verified_at' => now(),
            'last_verification_status' => 'failed',
            'last_verification_message' => 'HTTP 401 Unauthorized',
        ]);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateText')->once()->andReturn('Double-check the secret key was copied in full.');
        });

        Livewire::actingAs($this->admin)
            ->test(ConnectionsHealth::class)
            ->assertSuccessful()
            ->call('explain', 'payment_flutterwave')
            ->assertSet('aiExplanations.payment_flutterwave', 'Double-check the secret key was copied in full.');
    }

    public function test_a_non_admin_cannot_access_the_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(ConnectionsHealth::class)
            ->assertForbidden();
    }

    /**
     * The built-in GitHub sync feature registers with this same aggregator
     * rather than a parallel status screen — see
     * SettingsHealthChecker::gitHubSyncItem().
     */
    public function test_the_github_sync_item_appears_unconfigured_by_default(): void
    {
        $item = collect(app(SettingsHealthChecker::class)->items())->firstWhere('key', 'github_sync');

        $this->assertNotNull($item);
        $this->assertSame('System', $item['group']);
        $this->assertFalse($item['is_configured']);
        $this->assertNull($item['success']);
    }

    public function test_running_live_checks_verifies_github_sync_once_credentials_are_configured(): void
    {
        GitHubSyncSetting::current()->update([
            'repo_owner' => 'acme',
            'repo_name' => 'affilistack',
            'credentials' => ['personal_access_token' => 'ghp_fake'],
        ]);

        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        app(SettingsHealthChecker::class)->runLiveChecks();

        $settings = GitHubSyncSetting::current();
        $this->assertSame('failed', $settings->last_sync_status);
        $this->assertNotNull($settings->last_sync_at);

        $item = collect(app(SettingsHealthChecker::class)->items())->firstWhere('key', 'github_sync');
        $this->assertFalse($item['success']);
    }
}
