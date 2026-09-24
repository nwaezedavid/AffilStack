<?php

namespace Tests\Feature;

use App\Filament\Pages\PaymentGatewaySettings;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Services\Payments\PaymentCredentialAdvisor;
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

    public function test_explain_with_ai_only_runs_after_a_failed_verification_and_never_sends_the_secret(): void
    {
        config(['ai.openai.api_key' => 'test-key']);

        Http::fake([
            'api.stripe.com/v1/balance' => Http::response([], 401),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'That looks like a publishable key — use the secret key instead.']]],
            ], 200),
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(PaymentGatewaySettings::class)
            ->fillForm(['stripe' => ['is_enabled' => true, 'secret_key' => 'pk_test_wrong_type']])
            ->call('verify', 'stripe', app(PaymentGatewayManager::class))
            ->call('explainFailure', 'stripe', app(PaymentCredentialAdvisor::class));

        $this->assertSame(
            'That looks like a publishable key — use the secret key instead.',
            $component->get('aiExplanations')['stripe']
        );

        // The prompt sent to the AI carries only the gateway name and the
        // sanitized failure message — the entered credential never leaves
        // this app.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.openai.com')) {
                return true;
            }

            return ! str_contains(json_encode($request->data()), 'pk_test_wrong_type');
        });
    }

    /**
     * A Livewire component's public properties (here, the form's `$data`,
     * bound via ->statePath('data')) are serialized into the page's
     * wire:snapshot on every render — plain, view-source-visible text in
     * the response HTML, regardless of the widget itself rendering as a
     * masked `type="password"` input. Before the WritesMaskedCredentials
     * fix, mount()'s stateFor() filled every gateway's secret fields with
     * their real decrypted value, so a stored key was in the page's raw
     * HTML the instant the page loaded, before an admin touched anything.
     */
    public function test_mounting_the_page_does_not_leak_stored_secrets_into_the_rendered_snapshot(): void
    {
        PaymentGatewaySetting::forGateway('stripe')->update([
            'is_enabled' => true,
            'credentials' => [
                'secret_key' => 'sk_live_TOPSECRET99',
                'publishable_key' => 'pk_live_public_ok',
                'webhook_secret' => 'whsec_TOPSECRET99',
            ],
        ]);
        PaymentGatewaySetting::forGateway('flutterwave')->update([
            'is_enabled' => true,
            'credentials' => ['secret_key' => 'FLWSECK-TOPSECRET99', 'secret_hash' => 'flw-hash-TOPSECRET99'],
        ]);
        PaymentGatewaySetting::forGateway('paypal')->update([
            'is_enabled' => true,
            'credentials' => ['client_secret' => 'paypal-TOPSECRET99'],
        ]);

        $html = Livewire::actingAs($this->admin)
            ->test(PaymentGatewaySettings::class)
            ->html();

        foreach ([
            'sk_live_TOPSECRET99',
            'whsec_TOPSECRET99',
            'FLWSECK-TOPSECRET99',
            'flw-hash-TOPSECRET99',
            'paypal-TOPSECRET99',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $html, "Leaked secret [{$secret}] found in the page's rendered HTML/wire:snapshot.");
        }

        // A non-secret credential is fine to round-trip into the visible form.
        $this->assertStringContainsString('pk_live_public_ok', $html);
    }

    /**
     * Since a secret field never mounts with its real value (see above), a
     * blank submission must not be read as "clear the stored secret" —
     * only a non-empty submission should ever replace it.
     */
    public function test_saving_with_a_blank_secret_field_keeps_the_previously_stored_secret(): void
    {
        PaymentGatewaySetting::forGateway('stripe')->update([
            'is_enabled' => true,
            'credentials' => ['secret_key' => 'sk_live_KEEPME', 'publishable_key' => 'pk_live_old'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(PaymentGatewaySettings::class)
            ->fillForm(['stripe' => ['is_enabled' => true, 'publishable_key' => 'pk_live_new']])
            ->call('save')
            ->assertHasNoFormErrors();

        $stripe = PaymentGatewaySetting::forGateway('stripe');
        $this->assertSame('sk_live_KEEPME', $stripe->credential('secret_key'));
        $this->assertSame('pk_live_new', $stripe->credential('publishable_key'));
    }
}
