<?php

namespace Tests\Feature;

use App\Filament\Pages\ConnectionsHealth;
use App\Models\BrainAgentSetting;
use App\Models\GoogleOauthSetting;
use App\Models\HeyGenSetting;
use App\Models\InstagramSetting;
use App\Models\LinkedInOauthSetting;
use App\Models\PartnerStackSetting;
use App\Models\PaymentGatewaySetting;
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
}
