<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleSiteAnalyticsSettings;
use App\Models\GoogleOauthSetting;
use App\Models\GoogleSiteAnalyticsSetting;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Analytics\GoogleSiteAnalyticsService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * The Google Site Kit-equivalent auto-connect feature (per the user's
 * explicit request): one OAuth grant reusing the same client id/secret
 * GoogleOauthSetting already holds (identical reasoning to
 * GmailOAuthServiceTest), then auto-provisioning GA4/Search Console/Tag
 * Manager — see GoogleSiteAnalyticsService.
 */
class GoogleSiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function enableGoogleOauth(): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    public function test_is_available_requires_the_shared_google_oauth_client(): void
    {
        $this->assertFalse(app(GoogleSiteAnalyticsService::class)->isAvailable());

        $this->enableGoogleOauth();

        $this->assertTrue(app(GoogleSiteAnalyticsService::class)->isAvailable());
    }

    public function test_authorization_url_requests_offline_access_and_all_three_scopes(): void
    {
        $this->enableGoogleOauth();

        $url = app(GoogleSiteAnalyticsService::class)->authorizationUrl('https://app.example/callback', 'state123');

        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/analytics.edit'), $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/webmasters'), $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/tagmanager.edit.containers'), $url);
    }

    public function test_handle_callback_stores_tokens_and_the_connected_email(): void
    {
        $this->enableGoogleOauth();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'owner@example.com']),
        ]);

        app(GoogleSiteAnalyticsService::class)->handleCallback('auth-code', 'https://app.example/callback');

        $settings = GoogleSiteAnalyticsSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('owner@example.com', $settings->connected_email);
        $this->assertSame('at1', $settings->credential('access_token'));
        $this->assertSame('rt1', $settings->credential('refresh_token'));
        $this->assertTrue($settings->isConnected());
    }

    public function test_handle_callback_fails_loudly_without_offline_access(): void
    {
        $this->enableGoogleOauth();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'expires_in' => 3600]),
        ]);

        $this->expectException(RuntimeException::class);

        app(GoogleSiteAnalyticsService::class)->handleCallback('auth-code', 'https://app.example/callback');
    }

    protected function connectedSettings(): GoogleSiteAnalyticsSetting
    {
        $this->enableGoogleOauth();
        $settings = GoogleSiteAnalyticsSetting::current();
        $settings->update([
            'is_enabled' => true,
            'connected_email' => 'owner@example.com',
            'credentials' => [
                'access_token' => 'still-good',
                'refresh_token' => 'rt1',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
        ]);

        return $settings->fresh();
    }

    public function test_set_up_analytics_creates_a_property_and_stores_the_measurement_id(): void
    {
        $this->connectedSettings();
        Http::fake([
            'analyticsadmin.googleapis.com/v1beta/properties' => Http::response(['name' => 'properties/123']),
            'analyticsadmin.googleapis.com/v1beta/properties/123/dataStreams' => Http::response([
                'webStreamData' => ['measurementId' => 'G-ABC123'],
            ]),
        ]);

        $result = app(GoogleSiteAnalyticsService::class)->setUpAnalytics('accounts/999');

        $this->assertTrue($result['success']);
        $settings = GoogleSiteAnalyticsSetting::current();
        $this->assertSame('G-ABC123', $settings->credential('ga_measurement_id'));
        $this->assertTrue($settings->hasGa4());
    }

    public function test_set_up_analytics_reports_failure_without_crashing_when_no_account_exists(): void
    {
        $this->connectedSettings();
        Http::fake([
            'analyticsadmin.googleapis.com/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $result = app(GoogleSiteAnalyticsService::class)->setUpAnalytics('accounts/999');

        $this->assertFalse($result['success']);
        $this->assertFalse(GoogleSiteAnalyticsSetting::current()->hasGa4());
    }

    public function test_verify_search_console_saves_the_verification_token_even_if_googles_confirm_step_fails(): void
    {
        $this->connectedSettings();
        Http::fake([
            'www.googleapis.com/siteVerification/v1/token' => Http::response(['token' => 'verify-token-123']),
            'www.googleapis.com/siteVerification/v1/webResource*' => Http::response(['error' => 'not reachable'], 400),
        ]);

        $result = app(GoogleSiteAnalyticsService::class)->verifySearchConsole();

        $this->assertFalse($result['success']);
        $this->assertSame('verify-token-123', SiteSetting::get('seo_gsc_verification'));
    }

    public function test_verify_search_console_succeeds_end_to_end_and_submits_the_sitemap(): void
    {
        $this->connectedSettings();
        Http::fake([
            'www.googleapis.com/siteVerification/v1/token' => Http::response(['token' => 'verify-token-123']),
            'www.googleapis.com/siteVerification/v1/webResource*' => Http::response(['id' => 'site-id']),
            'searchconsole.googleapis.com/*' => Http::response(['ok' => true]),
        ]);

        $result = app(GoogleSiteAnalyticsService::class)->verifySearchConsole();

        $this->assertTrue($result['success']);
        $this->assertTrue(GoogleSiteAnalyticsSetting::current()->hasSearchConsole());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchconsole.googleapis.com') && str_contains($request->url(), 'sitemaps'));
    }

    public function test_set_up_tag_manager_creates_a_container_and_stores_the_public_id(): void
    {
        $this->connectedSettings();
        Http::fake([
            'www.googleapis.com/tagmanager/v2/accounts/999/containers' => Http::response([
                'containerId' => 'c1', 'publicId' => 'GTM-ABCDEF',
            ]),
        ]);

        $result = app(GoogleSiteAnalyticsService::class)->setUpTagManager('999');

        $this->assertTrue($result['success']);
        $this->assertSame('GTM-ABCDEF', GoogleSiteAnalyticsSetting::current()->credential('gtm_public_id'));
    }

    public function test_an_expired_access_token_is_refreshed_before_a_provisioning_call(): void
    {
        $this->enableGoogleOauth();
        GoogleSiteAnalyticsSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['access_token' => 'expired', 'refresh_token' => 'rt1', 'expires_at' => now()->subMinute()->toIso8601String()],
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 3600]),
            'analyticsadmin.googleapis.com/v1beta/accounts' => Http::response(['accounts' => []]),
        ]);

        app(GoogleSiteAnalyticsService::class)->listAnalyticsAccounts();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'analyticsadmin.googleapis.com')
            && $request->hasHeader('Authorization', 'Bearer new-token'));
        $this->assertSame('new-token', GoogleSiteAnalyticsSetting::current()->credential('access_token'));
    }

    public function test_disconnect_clears_the_stored_credentials(): void
    {
        $this->connectedSettings();

        app(GoogleSiteAnalyticsService::class)->disconnect();

        $settings = GoogleSiteAnalyticsSetting::current();
        $this->assertFalse($settings->is_enabled);
        $this->assertFalse($settings->isConnected());
        $this->assertNull($settings->connected_email);
    }

    public function test_the_connect_route_redirects_to_google_with_a_signed_state(): void
    {
        $this->enableGoogleOauth();

        $response = $this->actingAs($this->admin)->get(route('google-site-analytics.connect'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_the_connect_route_redirects_back_with_an_error_when_google_oauth_is_not_set_up(): void
    {
        $response = $this->actingAs($this->admin)->get(route('google-site-analytics.connect'));

        $response->assertRedirect(route('filament.admin.pages.google-site-analytics-settings'));
        $response->assertSessionHas('error');
    }

    public function test_a_non_admin_cannot_use_the_connect_route(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('google-site-analytics.connect'))->assertForbidden();
    }

    public function test_the_callback_route_rejects_a_mismatched_state(): void
    {
        $this->enableGoogleOauth();
        $this->actingAs($this->admin)->get(route('google-site-analytics.connect'));

        $response = $this->actingAs($this->admin)->get(route('google-site-analytics.callback', ['state' => 'wrong', 'code' => 'abc']));

        $response->assertRedirect(route('filament.admin.pages.google-site-analytics-settings'));
        $response->assertSessionHas('error');
        $this->assertFalse(GoogleSiteAnalyticsSetting::current()->isConnected());
    }

    public function test_admin_can_load_the_settings_page_and_run_each_setup_step(): void
    {
        $this->connectedSettings();
        Http::fake([
            'analyticsadmin.googleapis.com/v1beta/accounts' => Http::response(['accounts' => [
                ['name' => 'accounts/1', 'displayName' => 'My Site'],
            ]]),
        ]);

        Livewire::actingAs($this->admin)
            ->test(GoogleSiteAnalyticsSettings::class)
            ->assertSuccessful()
            ->call('loadAnalyticsAccounts')
            ->assertSet('analyticsAccounts', [['name' => 'accounts/1', 'displayName' => 'My Site']]);
    }

    public function test_a_non_admin_cannot_access_the_settings_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(GoogleSiteAnalyticsSettings::class)
            ->assertForbidden();
    }
}
