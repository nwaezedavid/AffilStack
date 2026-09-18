<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mirrors StripePaymentFlowTest/PaymentSignupFlowTest for the Paystack
 * gateway (audit item #3 — the Nigeria/naira rail). The distinguishing
 * behavior under test: every plan is priced in USD, but Paystack always
 * settles in NGN, so the checkout amount is converted via the admin-set
 * exchange rate and that converted figure — not the plan's USD price — is
 * what ends up on the PaymentTransaction row.
 */
class PaystackPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        config([
            'services.paystack.secret_key' => 'sk_test_fake',
            'services.paystack.usd_to_ngn_rate' => 1600,
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

    public function test_signup_creates_account_only_after_verified_paystack_payment(): void
    {
        $plan = $this->makePlan();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123'],
            ], 200),
        ]);

        // Paystack is only offered to a checkout resolved as Nigerian (audit
        // item #3) — see CheckoutCountryGatingTest for that gating itself.
        $response = $this->withSession(['checkout_country' => 'NG'])->post(route('registration.store', $plan), [
            'name' => 'Jane Marketer',
            'email' => 'jane@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly',
            'gateway' => 'paystack',
            'accepts_refund_policy' => '1',
        ]);

        $response->assertRedirect('https://checkout.paystack.com/abc123');

        // $67.00 USD * 1600 = ₦107,200.00 = 10,720,000 kobo — not the plan's
        // own USD cents. See PaymentGateway interface docblock.
        $pending = PendingSignup::where('email', 'jane@example.com')->firstOrFail();
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();
        $this->assertSame('paystack', $transaction->gateway);
        $this->assertSame('NGN', $transaction->currency);
        $this->assertSame(10720000, $transaction->amount_cents);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 998877,
                    'reference' => $transaction->tx_ref,
                    'status' => 'success',
                    'amount' => 10720000,
                    'currency' => 'NGN',
                    'customer' => ['email' => 'jane@example.com'],
                    'metadata' => [
                        'tx_ref' => $transaction->tx_ref,
                        'pending_signup_id' => (string) $pending->id,
                        'plan_id' => (string) $plan->id,
                        'billing_cycle' => 'monthly',
                    ],
                ],
            ], 200),
        ]);

        $callback = $this->get(route('registration.callback', [
            'gateway' => 'paystack',
            'reference' => $transaction->tx_ref,
        ]));

        $callback->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertAuthenticatedAs($user);

        $subscription = $user->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('paystack', $subscription->gateway);

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);
        $this->assertSame('998877', $transaction->gateway_tx_id);
        $this->assertSame($transaction->tx_ref, $transaction->gateway_reference);
    }

    public function test_paystack_webhook_requires_a_valid_signature(): void
    {
        $plan = $this->makePlan();
        $pending = PendingSignup::create([
            'name' => 'Sam Affiliate', 'email' => 'sam@example.com',
            'password' => bcrypt('whatever-secure'), 'plan_id' => $plan->id,
            'billing_cycle' => 'monthly', 'tx_ref' => 'affilstack_growth_monthly_test',
            'status' => 'pending', 'expires_at' => now()->addHours(24),
        ]);
        $transaction = PaymentTransaction::create([
            'pending_signup_id' => $pending->id, 'type' => 'signup', 'gateway' => 'paystack',
            'tx_ref' => $pending->tx_ref, 'amount_cents' => 10720000, 'currency' => 'NGN', 'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 554433,
                    'reference' => $transaction->tx_ref,
                    'status' => 'success',
                    'amount' => 10720000,
                    'currency' => 'NGN',
                    'customer' => ['email' => 'sam@example.com'],
                    'metadata' => [
                        'tx_ref' => $transaction->tx_ref,
                        'pending_signup_id' => (string) $pending->id,
                        'plan_id' => (string) $plan->id,
                        'billing_cycle' => 'monthly',
                    ],
                ],
            ], 200),
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => $transaction->tx_ref],
        ]);

        // Tampered signature is rejected.
        $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_x-paystack-signature' => 'not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(401);
        $this->assertSame(0, User::where('email', 'sam@example.com')->count());

        // A correctly signed payload is accepted and processed.
        $signature = hash_hmac('sha512', $payload, 'sk_test_fake');

        $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_x-paystack-signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $user = User::where('email', 'sam@example.com')->firstOrFail();
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());

        // A webhook retry for the same event is idempotent.
        $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_x-paystack-signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }
}
