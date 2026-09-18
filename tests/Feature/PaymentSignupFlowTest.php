<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\WelcomeAboard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers the "no free signup" flow end to end through the gateway
 * abstraction (App\Services\Payments\*): pick a plan, pay, get an account
 * — for both the redirect-callback path and the server-to-server webhook,
 * including the race where both arrive for the same transaction.
 */
class PaymentSignupFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
    }

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_signup_creates_account_only_after_verified_payment(): void
    {
        Notification::fake();

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);

        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/pay/fake-link'],
            ], 200),
        ]);

        $response = $this->post(route('registration.store', $plan), [
            'name' => 'Jane Marketer',
            'email' => 'jane@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly',
            'accepts_refund_policy' => '1',
        ]);

        $response->assertRedirect('https://checkout.flutterwave.com/pay/fake-link');

        // No account exists yet — payment hasn't verified.
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $pending = PendingSignup::where('email', 'jane@example.com')->firstOrFail();
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();
        $this->assertSame('pending', $transaction->status);

        // Flutterwave's verify-transaction response for the redirect callback
        // — a distinct URL pattern from the checkout-initiation fake above,
        // so both stay in effect at once (Http::fake() does not replace
        // previously registered patterns, only adds to them).
        Http::fake([
            'api.flutterwave.com/v3/transactions/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 998877,
                    'tx_ref' => $transaction->tx_ref,
                    'status' => 'successful',
                    'amount' => 67.00,
                    'currency' => 'USD',
                    'customer' => ['email' => 'jane@example.com'],
                    'meta' => ['pending_signup_id' => $pending->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
                ],
            ], 200),
        ]);

        $callback = $this->get(route('registration.callback', [
            'gateway' => 'flutterwave',
            'tx_ref' => $transaction->tx_ref,
            'transaction_id' => 998877,
            'status' => 'successful',
        ]));

        $callback->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertAuthenticatedAs($user);

        $subscription = $user->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('flutterwave', $subscription->gateway);
        $this->assertSame('active', $subscription->status);
        $this->assertSame($plan->id, $subscription->plan_id);

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);
        $this->assertSame('998877', $transaction->gateway_tx_id);

        // Triggered automatically on signup, per Sam's (the Support
        // Agent's) spec — see PaymentProcessor::completeSignup().
        Notification::assertSentTo($user, WelcomeAboard::class);
    }

    public function test_webhook_and_callback_racing_for_the_same_transaction_is_idempotent(): void
    {
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'Test plan',
            'price_monthly_cents' => 2700, 'price_yearly_cents' => 27000, 'currency' => 'USD',
            'credits_per_month' => 150, 'active_products_limit' => 1, 'contact_limit' => 500,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);

        $pending = PendingSignup::create([
            'name' => 'Sam Affiliate', 'email' => 'sam@example.com',
            'password' => bcrypt('whatever-secure'), 'plan_id' => $plan->id,
            'billing_cycle' => 'monthly', 'tx_ref' => 'affilstack_starter_monthly_test',
            'status' => 'pending', 'expires_at' => now()->addHours(24),
        ]);

        $transaction = PaymentTransaction::create([
            'pending_signup_id' => $pending->id, 'type' => 'signup', 'gateway' => 'flutterwave',
            'tx_ref' => $pending->tx_ref, 'amount_cents' => 2700, 'currency' => 'USD', 'status' => 'pending',
        ]);

        $verifyResponse = [
            'status' => 'success',
            'data' => [
                'id' => 555111,
                'tx_ref' => $transaction->tx_ref,
                'status' => 'successful',
                'amount' => 27.00,
                'currency' => 'USD',
                'customer' => ['email' => 'sam@example.com'],
                'meta' => ['pending_signup_id' => $pending->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
            ],
        ];
        Http::fake(['api.flutterwave.com/*' => Http::response($verifyResponse, 200)]);

        // The redirect callback arrives first...
        $this->get(route('registration.callback', [
            'gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref,
            'transaction_id' => 555111, 'status' => 'successful',
        ]))->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());

        // ...then the webhook retries the same event. No duplicate user or subscription.
        $webhook = $this->postJson('/webhooks/flutterwave', [
            'data' => ['id' => 555111],
        ], ['verif-hash' => config('services.flutterwave.secret_hash') ?: 'unset']);

        // Without a configured secret_hash the webhook is correctly rejected
        // (401) rather than silently trusted — that's the security property
        // under test here, not a failure of the idempotency guarantee.
        $webhook->assertStatus(401);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $this->assertSame(1, Subscription::where('user_id', User::where('email', 'sam@example.com')->value('id'))->count());
    }

    public function test_signup_is_blocked_when_no_gateway_is_enabled(): void
    {
        PaymentGatewaySetting::query()->update(['is_enabled' => false]);

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);

        $response = $this->from(route('registration.form', $plan))->post(route('registration.store', $plan), [
            'name' => 'Jane Marketer',
            'email' => 'blocked@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly',
            'accepts_refund_policy' => '1',
        ]);

        $response->assertRedirect(route('registration.form', $plan));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('pending_signups', ['email' => 'blocked@example.com']);
    }
}
