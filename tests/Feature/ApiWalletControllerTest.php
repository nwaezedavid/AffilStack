<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The API wallet's dashboard-side actions (see ApiWalletManagerTest for the
 * ledger itself). Mirrors CreditTopupTest's checkout/callback pattern
 * closely — same gateway plumbing, just crediting api_wallet_balance_cents
 * instead of credits_balance (PaymentProcessor::grantApiWalletTopup()).
 */
class ApiWalletControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true]);
        config([
            'services.flutterwave.secret_key' => 'flw_test_fake',
            'services.stripe.secret_key' => 'sk_test_fake',
            'services.paystack.secret_key' => 'sk_test_fake',
            'services.paypal.client_id' => 'client_fake',
            'services.paypal.client_secret' => 'secret_fake',
        ]);
    }

    protected function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_a_seat_cannot_checkout_or_update_wallet_settings(): void
    {
        $plan = Plan::factory()->sharedTeamPlan()->create();
        $owner = $this->makeUser();
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');

        $this->actingAs($seat)->post(route('api-access.wallet.checkout'), ['amount_cents' => 1000])->assertForbidden();
        $this->actingAs($seat)->patch(route('api-access.wallet.settings'), ['auto_recharge_enabled' => '1'])->assertForbidden();
    }

    public function test_checkout_creates_a_pending_api_wallet_topup_transaction_and_redirects(): void
    {
        $user = $this->makeUser();

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/wallet']], 200)]);

        $this->actingAs($user)
            ->post(route('api-access.wallet.checkout'), ['amount_cents' => 2500, 'gateway' => 'flutterwave'])
            ->assertRedirect('https://checkout.flutterwave.com/wallet');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();
        $this->assertSame('api_wallet_topup', $transaction->type);
        $this->assertSame(2500, $transaction->amount_cents);
        $this->assertSame('pending', $transaction->status);

        Http::assertSent(fn ($request) => data_get($request->data(), 'meta.type') === 'api_wallet_topup');
    }

    public function test_checkout_rejects_an_amount_below_the_configured_minimum(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('api-access.wallet.checkout'), ['amount_cents' => 500, 'gateway' => 'flutterwave'])
            ->assertSessionHasErrors('amount_cents');

        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_completing_a_topup_credits_the_wallet_and_saves_the_card_used(): void
    {
        $user = $this->makeUser();

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/wallet']], 200)]);
        $this->actingAs($user)->post(route('api-access.wallet.checkout'), ['amount_cents' => 2500, 'gateway' => 'flutterwave']);
        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();

        Http::fake(['api.flutterwave.com/v3/transactions/*/verify' => Http::response(['status' => 'success', 'data' => [
            'id' => 777222, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful',
            'amount' => 25, 'currency' => 'USD', 'customer' => ['email' => $user->email],
            'card' => ['token' => 'flw_card_tok', 'type' => 'visa', 'last_4digits' => '4242', 'expiry' => '12/28', 'country' => 'NIGERIA NG'],
            'meta' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'type' => 'api_wallet_topup'],
        ]], 200)]);

        $this->get(route('billing.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => '777222', 'status' => 'successful']))
            ->assertRedirect(route('api-access.index'))
            ->assertSessionHas('success', '$25.00 has been added to your API wallet.');

        $this->assertSame(2500, $user->fresh()->api_wallet_balance_cents);
        $this->assertSame('successful', $transaction->fresh()->status);
        $this->assertDatabaseHas('payment_methods', ['user_id' => $user->id, 'gateway' => 'flutterwave', 'last4' => '4242', 'country' => 'NG']);
    }

    public function test_update_settings_requires_a_saved_payment_method_to_enable_auto_recharge(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->patch(route('api-access.wallet.settings'), [
                'auto_recharge_enabled' => '1',
                'auto_recharge_threshold_cents' => 500,
                'auto_recharge_amount_cents' => 2000,
            ])
            ->assertSessionHas('error');

        $this->assertFalse($user->fresh()->api_wallet_auto_recharge_enabled);
    }

    public function test_update_settings_saves_auto_recharge_configuration_and_can_disable_it(): void
    {
        $user = $this->makeUser();
        $method = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '4242', 'gateway_token' => 'pm_1', 'is_default' => true]);

        $this->actingAs($user)
            ->patch(route('api-access.wallet.settings'), [
                'auto_recharge_enabled' => '1',
                'auto_recharge_threshold_cents' => 500,
                'auto_recharge_amount_cents' => 2000,
                'payment_method_id' => $method->id,
            ])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->api_wallet_auto_recharge_enabled);
        $this->assertSame(500, $user->api_wallet_auto_recharge_threshold_cents);
        $this->assertSame(2000, $user->api_wallet_auto_recharge_amount_cents);
        $this->assertSame($method->id, $user->api_wallet_payment_method_id);

        $this->actingAs($user)->patch(route('api-access.wallet.settings'), [])->assertSessionHas('success');

        $this->assertFalse($user->fresh()->api_wallet_auto_recharge_enabled);
        // Disabling doesn't wipe the previously-saved threshold/amount/card —
        // just the toggle — so re-enabling later doesn't force re-entering them.
        $this->assertSame($method->id, $user->fresh()->api_wallet_payment_method_id);
    }

    public function test_a_user_cannot_pick_someone_elses_payment_method_for_auto_recharge(): void
    {
        $user = $this->makeUser();
        $intruder = $this->makeUser();
        $othersMethod = PaymentMethod::create(['user_id' => $intruder->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '9999', 'gateway_token' => 'pm_other']);

        $this->actingAs($user)
            ->patch(route('api-access.wallet.settings'), [
                'auto_recharge_enabled' => '1',
                'payment_method_id' => $othersMethod->id,
            ])
            ->assertSessionHasErrors('payment_method_id');
    }
}
