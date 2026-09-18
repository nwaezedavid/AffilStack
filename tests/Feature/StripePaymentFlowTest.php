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
 * Mirrors PaymentSignupFlowTest but for the Stripe gateway: Checkout
 * Sessions (mode=subscription) via raw Http calls, resolved through the
 * same App\Services\Payments\* abstraction, plus signed-webhook handling.
 */
class StripePaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        config(['services.stripe.secret_key' => 'sk_test_fake', 'services.stripe.webhook_secret' => 'whsec_test_fake']);
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

    public function test_signup_creates_account_only_after_verified_stripe_payment(): void
    {
        $plan = $this->makePlan();

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            ], 200),
        ]);

        $response = $this->post(route('registration.store', $plan), [
            'name' => 'Jane Marketer',
            'email' => 'jane@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly',
            'gateway' => 'stripe',
            'accepts_refund_policy' => '1',
        ]);

        $response->assertRedirect('https://checkout.stripe.com/pay/cs_test_123');
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $pending = PendingSignup::where('email', 'jane@example.com')->firstOrFail();
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();
        $this->assertSame('stripe', $transaction->gateway);

        // Distinct URL pattern (with a path segment) from the create-session
        // fake above, so both stay registered at once — see
        // PaymentSignupFlowTest for why a shared pattern would silently
        // keep only the first Http::fake() in effect.
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response([
                'id' => 'cs_test_123',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_998877',
                'amount_total' => 6700,
                'currency' => 'usd',
                'customer' => 'cus_test_1',
                'subscription' => 'sub_test_1',
                'client_reference_id' => $transaction->tx_ref,
                'metadata' => [
                    'tx_ref' => $transaction->tx_ref,
                    'pending_signup_id' => (string) $pending->id,
                    'plan_id' => (string) $plan->id,
                    'billing_cycle' => 'monthly',
                ],
            ], 200),
        ]);

        $callback = $this->get(route('registration.callback', [
            'gateway' => 'stripe',
            'session_id' => 'cs_test_123',
        ]));

        $callback->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertAuthenticatedAs($user);

        $subscription = $user->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('stripe', $subscription->gateway);
        $this->assertSame('sub_test_1', $subscription->gateway_subscription_id);
        $this->assertSame('cus_test_1', $subscription->gateway_customer_id);

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);
        $this->assertSame('pi_test_998877', $transaction->gateway_tx_id);
    }

    public function test_stripe_webhook_requires_a_valid_signature(): void
    {
        $plan = $this->makePlan();
        $pending = PendingSignup::create([
            'name' => 'Sam Affiliate', 'email' => 'sam@example.com',
            'password' => bcrypt('whatever-secure'), 'plan_id' => $plan->id,
            'billing_cycle' => 'monthly', 'tx_ref' => 'affilstack_growth_monthly_test',
            'status' => 'pending', 'expires_at' => now()->addHours(24),
        ]);
        $transaction = PaymentTransaction::create([
            'pending_signup_id' => $pending->id, 'type' => 'signup', 'gateway' => 'stripe',
            'tx_ref' => $pending->tx_ref, 'amount_cents' => 6700, 'currency' => 'USD', 'status' => 'pending',
        ]);

        $event = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_456',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_555111',
                'amount_total' => 6700,
                'currency' => 'usd',
                'customer' => 'cus_test_2',
                'subscription' => 'sub_test_2',
                'metadata' => [
                    'tx_ref' => $transaction->tx_ref,
                    'pending_signup_id' => (string) $pending->id,
                    'plan_id' => (string) $plan->id,
                    'billing_cycle' => 'monthly',
                ],
            ]],
        ];
        $payload = json_encode($event);

        // Tampered signature is rejected.
        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => 't='.time().',v1=not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(401);
        $this->assertSame(0, User::where('email', 'sam@example.com')->count());

        // A correctly signed payload is accepted and processed.
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_fake');

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $user = User::where('email', 'sam@example.com')->firstOrFail();
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());

        // A webhook retry for the same event is idempotent.
        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(200);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }
}
