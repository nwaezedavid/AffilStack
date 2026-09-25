<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\PaystackGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PaymentGateway::chargeSavedToken() across all four gateways — the
 * off-session, no-redirect charge behind the API wallet's auto-recharge
 * (see ApiWalletManager::attemptAutoRecharge(), which is the only current
 * caller). Tested directly against each gateway here so the auto-recharge
 * tests in ApiWalletManagerTest can stay focused on the wallet's own ledger
 * logic rather than re-proving every gateway's request/response shape.
 */
class ChargeSavedTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true]);
        config([
            'services.stripe.secret_key' => 'sk_test_fake',
            'services.paystack.secret_key' => 'sk_test_fake',
            'services.paystack.usd_to_ngn_rate' => 1600,
            'services.flutterwave.secret_key' => 'flw_test_fake',
            'services.paypal.client_id' => 'client_fake',
            'services.paypal.client_secret' => 'secret_fake',
        ]);
    }

    // --- Stripe --------------------------------------------------------------

    public function test_stripe_charges_the_saved_payment_method_off_session(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'gateway_token' => 'pm_123', 'gateway_customer_id' => 'cus_123']);

        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'status' => 'succeeded'], 200)]);

        $result = app(StripeGateway::class)->chargeSavedToken($method, 2000, 'USD', 'AffilStack API wallet auto-recharge');

        $this->assertTrue($result['success']);
        $this->assertSame('pi_1', $result['reference']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/payment_intents')
            && $request['payment_method'] === 'pm_123'
            && $request['customer'] === 'cus_123'
            && $request['off_session'] === 'true'
            && $request['confirm'] === 'true'
            && (int) $request['amount'] === 2000);
    }

    public function test_stripe_reports_failure_when_the_card_is_declined(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'gateway_token' => 'pm_declined']);

        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['error' => ['message' => 'Your card was declined.']], 402)]);

        $result = app(StripeGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertFalse($result['success']);
        $this->assertSame('Your card was declined.', $result['message']);
    }

    public function test_stripe_reports_failure_when_no_token_is_on_file(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card']);

        Http::fake();

        $result = app(StripeGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    // --- Paystack --------------------------------------------------------------

    public function test_paystack_charges_the_authorization_code_converted_to_naira(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'paystack', 'type' => 'card', 'gateway_token' => 'AUTH_123']);

        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => 'ref_1']], 200)]);

        $result = app(PaystackGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertTrue($result['success']);
        $this->assertSame('ref_1', $result['reference']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/charge_authorization')
            && $request['authorization_code'] === 'AUTH_123'
            && $request['email'] === 'buyer@example.com'
            && (int) $request['amount'] === (int) round(20 * 1600 * 100));
    }

    public function test_paystack_reports_failure_when_declined(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'paystack', 'type' => 'card', 'gateway_token' => 'AUTH_declined']);

        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'failed', 'gateway_response' => 'Insufficient Funds']], 200)]);

        $result = app(PaystackGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient Funds', $result['message']);
    }

    // --- Flutterwave --------------------------------------------------------------

    public function test_flutterwave_charges_the_saved_token_with_the_stored_country(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'flutterwave', 'type' => 'card', 'gateway_token' => 'flw_tok_1', 'country' => 'NG']);

        Http::fake(['api.flutterwave.com/v3/tokenized-charges' => Http::response(['status' => 'success', 'data' => ['id' => 999, 'status' => 'successful']], 200)]);

        $result = app(FlutterwaveGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/tokenized-charges')
            && $request['token'] === 'flw_tok_1'
            && $request['country'] === 'NG'
            && $request['first_name'] === 'Ada'
            && $request['last_name'] === 'Lovelace');
    }

    public function test_flutterwave_fails_gracefully_when_the_saved_card_has_no_stored_country(): void
    {
        $user = User::factory()->create();
        // Simulates a card saved before this feature existed — normalize()
        // now always captures country going forward, but older rows won't
        // have it, and the tokenized-charges endpoint requires it.
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'flutterwave', 'type' => 'card', 'gateway_token' => 'flw_tok_old', 'country' => null]);

        Http::fake();

        $result = app(FlutterwaveGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('reconnect the card', $result['message']);
        Http::assertNothingSent();
    }

    public function test_flutterwave_treats_a_pending_status_as_a_failure_since_no_otp_flow_is_possible_unattended(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'flutterwave', 'type' => 'card', 'gateway_token' => 'flw_tok_otp', 'country' => 'NG']);

        Http::fake(['api.flutterwave.com/v3/tokenized-charges' => Http::response(['status' => 'success', 'data' => ['id' => 1000, 'status' => 'pending']], 200)]);

        $result = app(FlutterwaveGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('additional verification', $result['message']);
    }

    // --- PayPal --------------------------------------------------------------

    public function test_paypal_always_reports_unsupported_without_making_any_request(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'paypal', 'type' => 'paypal', 'label' => 'buyer@example.com']);

        Http::fake();

        $result = app(PayPalGateway::class)->chargeSavedToken($method, 2000, 'USD', 'desc');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('doesn\'t support automatic recharges', $result['message']);
        Http::assertNothingSent();
    }

    public function test_the_gateway_manager_resolves_the_correct_gateway_for_a_saved_methods_charge(): void
    {
        $user = User::factory()->create();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'gateway_token' => 'pm_1', 'gateway_customer_id' => 'cus_1']);

        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_2', 'status' => 'succeeded'], 200)]);

        $result = app(PaymentGatewayManager::class)->get($method->gateway)->chargeSavedToken($method, 500, 'USD', 'desc');

        $this->assertTrue($result['success']);
    }
}
