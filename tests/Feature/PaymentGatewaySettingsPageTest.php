<?php

namespace Tests\Feature;

use App\Filament\Pages\PaymentGatewaySettings;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin "Payment Gateways" Filament page (task #82): enable/disable
 * each gateway, store credentials (encrypted via PaymentGatewaySetting),
 * and verify them against the live API before relying on them.
 */
class PaymentGatewaySettingsPageTest extends TestCase
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

    public function test_admin_can_enable_a_gateway_and_save_its_credentials(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PaymentGatewaySettings::class)
            ->fillForm([
                'flutterwave' => [
                    'is_enabled' => true,
                    'secret_key' => 'FLWSECK_TEST-abc123',
                    'public_key' => 'FLWPUBK_TEST-xyz',
                    'secret_hash' => 'my-webhook-hash',
                ],
                'stripe' => ['is_enabled' => false],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $flutterwave = PaymentGatewaySetting::forGateway('flutterwave');
        $this->assertTrue($flutterwave->is_enabled);
        $this->assertSame('FLWSECK_TEST-abc123', $flutterwave->credential('secret_key'));
        $this->assertSame('my-webhook-hash', $flutterwave->credential('secret_hash'));

        // Encrypted at rest — the raw database column must not contain the
        // plaintext secret.
        $raw = \DB::table('payment_gateway_settings')->where('gateway', 'flutterwave')->value('credentials');
        $this->assertStringNotContainsString('FLWSECK_TEST-abc123', (string) $raw);

        $this->assertFalse(PaymentGatewaySetting::forGateway('stripe')->is_enabled);
    }

    public function test_verify_action_saves_current_fields_then_reports_the_gateway_response(): void
    {
        Http::fake([
            'api.stripe.com/v1/balance' => Http::response([], 401),
        ]);

        Livewire::actingAs($this->admin)
            ->test(PaymentGatewaySettings::class)
            ->fillForm([
                'stripe' => ['is_enabled' => true, 'secret_key' => 'sk_test_bad'],
            ])
            ->call('verify', 'stripe', app(PaymentGatewayManager::class));

        $stripe = PaymentGatewaySetting::forGateway('stripe');
        $this->assertSame('sk_test_bad', $stripe->credential('secret_key'));
        $this->assertSame('failed', $stripe->last_verification_status);
        $this->assertNotNull($stripe->last_verified_at);
        $this->assertStringContainsString('401', $stripe->last_verification_message);
    }
}
