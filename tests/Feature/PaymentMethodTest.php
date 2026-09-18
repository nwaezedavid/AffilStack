<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentMethodRecorder;
use App\Services\Payments\SubscriptionRenewalService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Saved payment methods (audit item #2) — a PaymentMethod row is captured
 * passively from every gateway's own successful-charge response
 * (PaymentMethodRecorder), never entered directly by the user. Covers each
 * gateway's own shape of "reusable payment detail", the billing-page
 * default/remove actions, and Stripe's alternate-card renewal retry.
 */
class PaymentMethodTest extends TestCase
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
            'services.paystack.usd_to_ngn_rate' => 1600,
            'services.paypal.client_id' => 'client_fake',
            'services.paypal.client_secret' => 'secret_fake',
        ]);
    }

    protected function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_flutterwave_payment_saves_a_reusable_card(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();

        // Distinct URL patterns per phase — a shared wildcard would let the
        // first-registered fake keep matching the verify call too. See
        // StripePaymentFlowTest's note on the same trap.
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/xyz']], 200)]);

        $this->actingAs($user)->post(route('billing.checkout', $plan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'flutterwave',
        ])->assertRedirect('https://checkout.flutterwave.com/xyz');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();

        Http::fake(['api.flutterwave.com/v3/transactions/*/verify' => Http::response(['status' => 'success', 'data' => [
            'id' => 998877, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful',
            'amount' => 67, 'currency' => 'USD', 'customer' => ['email' => $user->email],
            'meta' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => 'monthly'],
            'flw_ref' => 'FLW-REF-1',
            'card' => ['type' => 'VISA', 'last_4digits' => '4242', 'expiry' => '12/27', 'token' => 'flw_card_token_1'],
        ]], 200)]);

        $this->get(route('billing.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => '998877', 'status' => 'successful']))
            ->assertRedirect(route('dashboard'));

        $method = PaymentMethod::where('user_id', $user->id)->sole();
        $this->assertSame('flutterwave', $method->gateway);
        $this->assertSame('card', $method->type);
        $this->assertSame('visa', $method->brand);
        $this->assertSame('4242', $method->last4);
        $this->assertSame(12, $method->exp_month);
        // Flutterwave's card.expiry is "MM/YY" — normalized to a 4-digit
        // year so it's consistent with Paystack/Stripe. See
        // FlutterwaveGateway::normalize().
        $this->assertSame(2027, $method->exp_year);
        $this->assertTrue($method->is_default, 'The first saved method should become the default automatically.');
    }

    public function test_paystack_reusable_authorization_is_saved_but_a_non_reusable_one_is_not(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();

        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123'],
        ], 200)]);

        $this->actingAs($user)->withSession(['checkout_country' => 'NG'])->post(route('billing.checkout', $plan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'paystack',
        ])->assertRedirect('https://checkout.paystack.com/abc123');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();

        // Not reusable — e.g. a bank transfer/USSD charge — no method saved.
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'id' => 1, 'reference' => $transaction->tx_ref, 'status' => 'success',
            'amount' => $transaction->amount_cents, 'currency' => 'NGN', 'customer' => ['email' => $user->email],
            'metadata' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => 'monthly'],
            'authorization' => ['reusable' => false, 'authorization_code' => 'AUTH_nope'],
        ]], 200)]);

        $this->withSession(['checkout_country' => 'NG'])
            ->get(route('billing.callback', ['gateway' => 'paystack', 'reference' => $transaction->tx_ref]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, PaymentMethod::where('user_id', $user->id)->count());
    }

    public function test_paystack_reusable_authorization_is_saved(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();

        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123'],
        ], 200)]);

        $this->actingAs($user)->withSession(['checkout_country' => 'NG'])->post(route('billing.checkout', $plan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'paystack',
        ])->assertRedirect('https://checkout.paystack.com/abc123');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'id' => 2, 'reference' => $transaction->tx_ref, 'status' => 'success',
            'amount' => $transaction->amount_cents, 'currency' => 'NGN', 'customer' => ['email' => $user->email],
            'metadata' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'plan_id' => (string) $plan->id, 'billing_cycle' => 'monthly'],
            'authorization' => [
                'reusable' => true, 'authorization_code' => 'AUTH_yes123',
                'card_type' => 'mastercard', 'last4' => '4444', 'exp_month' => '08', 'exp_year' => '2028',
            ],
        ]], 200)]);

        $this->withSession(['checkout_country' => 'NG'])
            ->get(route('billing.callback', ['gateway' => 'paystack', 'reference' => $transaction->tx_ref]))
            ->assertRedirect(route('dashboard'));

        $method = PaymentMethod::where('user_id', $user->id)->sole();
        $this->assertSame('paystack', $method->gateway);
        $this->assertSame('mastercard', $method->brand);
        $this->assertSame('4444', $method->last4);
        $this->assertSame('AUTH_yes123', $method->gateway_token);
    }

    public function test_paypal_payment_saves_the_payer_account_as_a_method(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();
        $capturedOrder = [];

        Http::fake(function ($request) use (&$capturedOrder) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'A21AAtesttoken', 'expires_in' => 32400], 200);
            }

            if (str_ends_with($url, '/v2/checkout/orders') && $method === 'POST') {
                return Http::response([
                    'id' => 'ORDER999', 'status' => 'CREATED',
                    'links' => [['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=ORDER999']],
                ], 201);
            }

            if (str_contains($url, '/v2/checkout/orders/ORDER999/capture') && $method === 'POST') {
                return Http::response($capturedOrder, 200);
            }

            if (str_contains($url, '/v2/checkout/orders/ORDER999') && $method === 'GET') {
                return Http::response(['id' => 'ORDER999', 'status' => 'APPROVED'], 200);
            }

            return Http::response([], 404);
        });

        $this->actingAs($user)->post(route('billing.checkout', $plan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'paypal',
        ])->assertRedirect('https://www.paypal.com/checkoutnow?token=ORDER999');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();

        $capturedOrder = [
            'id' => 'ORDER999', 'status' => 'COMPLETED',
            'payer' => ['email_address' => $user->email, 'payer_id' => 'PAYERID1'],
            'purchase_units' => [[
                'reference_id' => $transaction->tx_ref,
                'custom_id' => json_encode(['tx_ref' => $transaction->tx_ref, 'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly']),
                'payments' => ['captures' => [['id' => 'CAP1', 'amount' => ['value' => '67.00', 'currency_code' => 'USD']]]],
            ]],
        ];

        $this->get(route('billing.callback', ['gateway' => 'paypal', 'token' => 'ORDER999']))
            ->assertRedirect(route('dashboard'));

        $method = PaymentMethod::where('user_id', $user->id)->sole();
        $this->assertSame('paypal', $method->gateway);
        $this->assertSame('paypal', $method->type);
        $this->assertSame($user->email, $method->label);
        $this->assertSame('PAYERID1', $method->gateway_customer_id);
    }

    public function test_a_second_saved_method_never_becomes_default_automatically(): void
    {
        $user = $this->makeUser();
        PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'flutterwave', 'type' => 'card', 'brand' => 'visa', 'last4' => '1111', 'gateway_token' => 'tok_1', 'is_default' => true]);

        app(PaymentMethodRecorder::class)->record($user, 'stripe', [
            'payment_method' => ['type' => 'card', 'brand' => 'mastercard', 'last4' => '2222', 'exp_month' => 1, 'exp_year' => 2030, 'token' => 'tok_2'],
        ]);

        $this->assertSame(2, PaymentMethod::where('user_id', $user->id)->count());
        $this->assertSame(1, PaymentMethod::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_user_can_set_a_different_default_payment_method(): void
    {
        $user = $this->makeUser();
        PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'flutterwave', 'type' => 'card', 'last4' => '1111', 'gateway_token' => 'tok_1', 'is_default' => true]);
        $second = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '2222', 'gateway_token' => 'tok_2', 'is_default' => false]);

        $this->actingAs($user)->patch(route('payment-methods.set-default', $second))
            ->assertRedirect(route('billing.index'));

        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame(1, PaymentMethod::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_user_can_remove_a_payment_method_and_default_moves_to_another(): void
    {
        $user = $this->makeUser();
        $first = PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'flutterwave', 'type' => 'card', 'last4' => '1111', 'gateway_token' => 'tok_1', 'is_default' => true]);
        PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '2222', 'gateway_token' => 'tok_2', 'is_default' => false]);

        $this->actingAs($user)->delete(route('payment-methods.destroy', $first))
            ->assertRedirect(route('billing.index'));

        $this->assertSame(1, PaymentMethod::where('user_id', $user->id)->count());
        $this->assertSame(1, PaymentMethod::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_a_user_cannot_modify_another_users_payment_method(): void
    {
        $owner = $this->makeUser();
        $intruder = $this->makeUser();
        $method = PaymentMethod::create(['user_id' => $owner->id, 'gateway' => 'flutterwave', 'type' => 'card', 'last4' => '1111', 'gateway_token' => 'tok_1', 'is_default' => true]);

        $this->actingAs($intruder)->patch(route('payment-methods.set-default', $method))->assertForbidden();
        $this->actingAs($intruder)->delete(route('payment-methods.destroy', $method))->assertForbidden();
        $this->assertNotNull($method->fresh());
    }

    public function test_a_failed_stripe_renewal_retries_an_alternate_saved_card_before_marking_past_due(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();
        $subscription = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'gateway_subscription_id' => 'sub_123',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now(),
        ]);
        PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '9999', 'gateway_token' => 'pm_backup', 'is_default' => false, 'last_used_at' => now()->subDay()]);

        Http::fake(['api.stripe.com/v1/invoices/*/pay' => Http::response(['status' => 'paid'], 200)]);

        app(SubscriptionRenewalService::class)->handleStripeEvent([
            'kind' => 'payment_failed',
            'gateway_subscription_id' => 'sub_123',
            'gateway_invoice_id' => 'in_failed_1',
            'raw' => [],
        ]);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status, 'A successful retry with an alternate card should not mark the subscription past due.');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices/in_failed_1/pay') && $request['payment_method'] === 'pm_backup');
    }

    public function test_a_failed_stripe_renewal_marks_past_due_when_no_alternate_card_works(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser();
        $subscription = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'gateway_subscription_id' => 'sub_456',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now(),
        ]);
        PaymentMethod::create(['user_id' => $user->id, 'gateway' => 'stripe', 'type' => 'card', 'last4' => '9999', 'gateway_token' => 'pm_backup', 'is_default' => false]);

        Http::fake(['api.stripe.com/v1/invoices/*/pay' => Http::response(['status' => 'requires_action'], 200)]);

        app(SubscriptionRenewalService::class)->handleStripeEvent([
            'kind' => 'payment_failed',
            'gateway_subscription_id' => 'sub_456',
            'gateway_invoice_id' => 'in_failed_2',
            'raw' => [],
        ]);

        $this->assertSame('past_due', $subscription->fresh()->status);
    }
}
