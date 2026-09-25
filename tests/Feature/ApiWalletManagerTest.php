<?php

namespace Tests\Feature;

use App\Models\ApiWalletTransaction;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\ApiWallet\ApiWalletManager;
use App\Services\ApiWallet\InsufficientApiWalletBalanceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ApiWalletManager — the single choke point for the API usage prepay
 * wallet's ledger (see ApiWalletMeteringTest for the HTTP-layer/middleware
 * behavior built on top of it). Mirrors CreditManagerTest's shape where one
 * exists, plus dedicated coverage for the one thing credits don't need:
 * attemptAutoRecharge().
 */
class ApiWalletManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function manager(): ApiWalletManager
    {
        return app(ApiWalletManager::class);
    }

    // --- Balance / charge / top-up / refund --------------------------------

    public function test_balance_and_has_enough_reflect_the_wallet_column(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 500]);

        $this->assertSame(500, $this->manager()->balance($user));
        $this->assertTrue($this->manager()->hasEnough($user, 500));
        $this->assertFalse($this->manager()->hasEnough($user, 501));
    }

    public function test_charge_debits_the_wallet_and_records_a_ledger_entry(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 1000]);

        $transaction = $this->manager()->charge($user, 75, 'api:test');

        $this->assertSame(925, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame('usage', $transaction->type);
        $this->assertSame(-75, $transaction->amount_cents);
        $this->assertSame(925, $transaction->balance_after_cents);
        $this->assertSame('api:test', $transaction->description);
    }

    public function test_charge_throws_when_balance_is_insufficient_and_auto_recharge_is_off(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 10]);

        $this->expectException(InsufficientApiWalletBalanceException::class);

        $this->manager()->charge($user, 75, 'api:test');
    }

    public function test_top_up_credits_the_wallet_records_a_ledger_entry_and_fires_the_topped_up_webhook(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 0]);
        $endpoint = WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://example.com/hook', 'secret' => WebhookEndpoint::generateSecret(), 'events' => ['api_wallet.topped_up'], 'is_active' => true]);

        $transaction = $this->manager()->topUp($user, 2000, 'api_wallet_topup_purchase');

        $this->assertSame(2000, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame('topup', $transaction->type);
        $this->assertSame(2000, $transaction->amount_cents);
        $this->assertDatabaseHas('webhook_deliveries', ['webhook_endpoint_id' => $endpoint->id, 'event' => 'api_wallet.topped_up']);
    }

    public function test_refund_credits_the_wallet_without_firing_the_topped_up_webhook(): void
    {
        $user = User::factory()->create(['api_wallet_balance_cents' => 100]);
        WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://example.com/hook', 'secret' => WebhookEndpoint::generateSecret(), 'events' => ['api_wallet.topped_up'], 'is_active' => true]);

        $transaction = $this->manager()->refund($user, 75, 'api:test:request_failed');

        $this->assertSame(175, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame('refund', $transaction->type);
        $this->assertDatabaseMissing('webhook_deliveries', ['event' => 'api_wallet.topped_up']);
    }

    public function test_charge_fires_the_low_balance_webhook_only_once_when_crossing_the_threshold(): void
    {
        config(['api_billing.low_balance_threshold_cents' => 500]);
        $user = User::factory()->create(['api_wallet_balance_cents' => 600]);
        $endpoint = WebhookEndpoint::create(['user_id' => $user->id, 'url' => 'https://example.com/hook', 'secret' => WebhookEndpoint::generateSecret(), 'events' => ['api_wallet.low_balance'], 'is_active' => true]);

        // 600 -> 550: still above the 500 threshold, no crossing yet.
        $this->manager()->charge($user, 50, 'api:test');
        $this->assertSame(0, $endpoint->deliveries()->count());

        // 550 -> 480: crosses the threshold — fires exactly once.
        $this->manager()->charge($user, 70, 'api:test');
        $this->assertSame(1, $endpoint->deliveries()->count());

        // 480 -> 430: already below threshold — must NOT fire again.
        $this->manager()->charge($user, 50, 'api:test');
        $this->assertSame(1, $endpoint->fresh()->deliveries()->count());
    }

    // --- billableUser() resolution (a team seat's calls hit the owner) -----

    public function test_a_seats_charge_and_refund_draw_from_the_owners_wallet(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = User::factory()->create(['api_wallet_balance_cents' => 1000]);
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member', 'api_wallet_balance_cents' => 0]);

        $this->assertSame(1000, $this->manager()->balance($seat));

        $charge = $this->manager()->charge($seat, 75, 'api:test');
        $this->assertSame($owner->id, $charge->user_id);
        $this->assertSame(925, $owner->fresh()->api_wallet_balance_cents);
        $this->assertSame(0, $seat->fresh()->api_wallet_balance_cents);

        $refund = $this->manager()->refund($seat, 75, 'api:test:request_failed');
        $this->assertSame($owner->id, $refund->user_id);
        $this->assertSame(1000, $owner->fresh()->api_wallet_balance_cents);
    }

    // --- attemptAutoRecharge() ----------------------------------------------

    protected function enableGatewaysForAutoRecharge(): void
    {
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

    public function test_attempt_auto_recharge_returns_false_when_disabled(): void
    {
        $user = User::factory()->create(['api_wallet_auto_recharge_enabled' => false]);

        $this->assertFalse($this->manager()->attemptAutoRecharge($user));
    }

    public function test_attempt_auto_recharge_returns_false_when_no_card_is_saved(): void
    {
        $user = User::factory()->create(['api_wallet_auto_recharge_enabled' => true, 'api_wallet_auto_recharge_amount_cents' => 2000]);

        $this->assertFalse($this->manager()->attemptAutoRecharge($user));
    }

    public function test_a_charge_attempts_auto_recharge_first_and_succeeds_when_the_saved_card_is_charged(): void
    {
        $this->enableGatewaysForAutoRecharge();
        $user = User::factory()->create(['api_wallet_balance_cents' => 0, 'api_wallet_auto_recharge_enabled' => true, 'api_wallet_auto_recharge_amount_cents' => 2000]);
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'brand' => 'visa', 'last4' => '4242', 'gateway_token' => 'pm_saved', 'gateway_customer_id' => 'cus_saved', 'is_default' => true]);
        $user->update(['api_wallet_payment_method_id' => $method->id]);

        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_auto', 'status' => 'succeeded'], 200)]);

        $transaction = $this->manager()->charge($user, 75, 'api:test');

        // Auto-recharged $20, then the original $0.75 call was debited from it.
        $this->assertSame(1925, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame(-75, $transaction->amount_cents);
        $this->assertDatabaseHas('payment_transactions', ['user_id' => $user->id, 'type' => 'api_wallet_auto_recharge', 'gateway' => 'stripe', 'amount_cents' => 2000, 'status' => 'successful']);
        $autoRecharge = ApiWalletTransaction::where('user_id', $user->id)->where('type', 'auto_recharge')->sole();
        $this->assertSame(2000, $autoRecharge->amount_cents);
    }

    public function test_a_charge_still_throws_when_the_saved_card_is_declined(): void
    {
        $this->enableGatewaysForAutoRecharge();
        $user = User::factory()->create(['api_wallet_balance_cents' => 0, 'api_wallet_auto_recharge_enabled' => true, 'api_wallet_auto_recharge_amount_cents' => 2000]);
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '0002', 'gateway_token' => 'pm_declined', 'is_default' => true]);
        $user->update(['api_wallet_payment_method_id' => $method->id]);

        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['error' => ['message' => 'Your card was declined.']], 402)]);

        $this->expectException(InsufficientApiWalletBalanceException::class);

        $this->manager()->charge($user, 75, 'api:test');
    }

    public function test_attempt_auto_recharge_with_a_paypal_method_fails_gracefully_without_throwing(): void
    {
        $this->enableGatewaysForAutoRecharge();
        $user = User::factory()->create(['api_wallet_auto_recharge_enabled' => true, 'api_wallet_auto_recharge_amount_cents' => 2000]);
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'paypal', 'type' => 'paypal', 'label' => 'buyer@example.com', 'is_default' => true]);
        $user->update(['api_wallet_payment_method_id' => $method->id]);

        $this->assertFalse($this->manager()->attemptAutoRecharge($user));
        $this->assertSame(0, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame(0, PaymentTransaction::count());
    }
}
