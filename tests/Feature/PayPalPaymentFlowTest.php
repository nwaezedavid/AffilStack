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
 * Mirrors StripePaymentFlowTest for PayPal (audit item #3 — the
 * international/USD rail). PayPal's Orders API has no "meta" field like
 * Flutterwave/Stripe, so plan/user/billing-cycle context is round-tripped
 * through purchase_units[0].custom_id — this is what's under test in the
 * signup flow, alongside the GET-then-capture order resolution.
 *
 * A single Http::fake() closure is registered once per test and reads its
 * response data back off mutable test properties, rather than calling
 * Http::fake() again mid-test — a second call doesn't replace the first
 * here (closure-based fakes accumulate and the earliest one always
 * matches), so a later call would silently never be reached. See
 * StripePaymentFlowTest's own note on the equivalent array-pattern trap.
 */
class PayPalPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    protected array $capturedOrder = [];

    protected string $webhookVerificationStatus = 'SUCCESS';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true]);
        config([
            'services.paypal.client_id' => 'client_fake',
            'services.paypal.client_secret' => 'secret_fake',
            'services.paypal.webhook_id' => 'WH-fake-123',
        ]);

        $this->fakePayPal();
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

    protected function fakePayPal(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'A21AAtesttoken', 'expires_in' => 32400], 200);
            }

            if (str_contains($url, '/v1/notifications/verify-webhook-signature')) {
                return Http::response(['verification_status' => $this->webhookVerificationStatus], 200);
            }

            if (str_ends_with($url, '/v2/checkout/orders') && $method === 'POST') {
                return Http::response([
                    'id' => 'ORDER123',
                    'status' => 'CREATED',
                    'links' => [['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=ORDER123']],
                ], 201);
            }

            if (str_contains($url, '/v2/checkout/orders/ORDER123/capture') && $method === 'POST') {
                return Http::response($this->capturedOrder, 200);
            }

            if (str_contains($url, '/v2/checkout/orders/ORDER123') && $method === 'GET') {
                return Http::response(['id' => 'ORDER123', 'status' => 'APPROVED'], 200);
            }

            return Http::response([], 404);
        });
    }

    public function test_signup_creates_account_only_after_verified_paypal_payment(): void
    {
        $plan = $this->makePlan();

        $response = $this->post(route('registration.store', $plan), [
            'name' => 'Jane Marketer',
            'email' => 'jane@example.com',
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly',
            'gateway' => 'paypal',
            'accepts_refund_policy' => '1',
        ]);

        $response->assertRedirect('https://www.paypal.com/checkoutnow?token=ORDER123');

        $pending = PendingSignup::where('email', 'jane@example.com')->firstOrFail();
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();
        $this->assertSame('paypal', $transaction->gateway);
        $this->assertSame('USD', $transaction->currency);
        $this->assertSame(6700, $transaction->amount_cents);

        $this->capturedOrder = [
            'id' => 'ORDER123',
            'status' => 'COMPLETED',
            'payer' => ['email_address' => 'jane@example.com'],
            'purchase_units' => [[
                'reference_id' => $transaction->tx_ref,
                'custom_id' => json_encode([
                    'tx_ref' => $transaction->tx_ref,
                    'pending_signup_id' => $pending->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => 'monthly',
                ]),
                'payments' => ['captures' => [[
                    'id' => 'CAP998877',
                    'amount' => ['value' => '67.00', 'currency_code' => 'USD'],
                ]]],
            ]],
        ];

        $callback = $this->get(route('registration.callback', [
            'gateway' => 'paypal',
            'token' => 'ORDER123',
        ]));

        $callback->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertAuthenticatedAs($user);

        $subscription = $user->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame('paypal', $subscription->gateway);

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);
        $this->assertSame('CAP998877', $transaction->gateway_tx_id);
    }

    public function test_paypal_webhook_requires_a_valid_signature(): void
    {
        $plan = $this->makePlan();
        $pending = PendingSignup::create([
            'name' => 'Sam Affiliate', 'email' => 'sam@example.com',
            'password' => bcrypt('whatever-secure'), 'plan_id' => $plan->id,
            'billing_cycle' => 'monthly', 'tx_ref' => 'affilstack_growth_monthly_test',
            'status' => 'pending', 'expires_at' => now()->addHours(24),
        ]);
        $transaction = PaymentTransaction::create([
            'pending_signup_id' => $pending->id, 'type' => 'signup', 'gateway' => 'paypal',
            'tx_ref' => $pending->tx_ref, 'amount_cents' => 6700, 'currency' => 'USD', 'status' => 'pending',
        ]);

        $this->capturedOrder = [
            'id' => 'ORDER123',
            'status' => 'COMPLETED',
            'payer' => ['email_address' => 'sam@example.com'],
            'purchase_units' => [[
                'reference_id' => $transaction->tx_ref,
                'custom_id' => json_encode([
                    'tx_ref' => $transaction->tx_ref,
                    'pending_signup_id' => $pending->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => 'monthly',
                ]),
                'payments' => ['captures' => [[
                    'id' => 'CAP554433',
                    'amount' => ['value' => '67.00', 'currency_code' => 'USD'],
                ]]],
            ]],
        ];

        $payload = json_encode([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'ORDER123'],
        ]);

        $headers = [
            'HTTP_paypal-transmission-id' => 'fake',
            'HTTP_paypal-transmission-time' => now()->toIso8601String(),
            'HTTP_paypal-cert-url' => 'https://api.paypal.com/cert',
            'HTTP_paypal-auth-algo' => 'SHA256withRSA',
            'HTTP_paypal-transmission-sig' => 'not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ];

        // A webhook PayPal's own verification endpoint rejects is rejected.
        $this->webhookVerificationStatus = 'FAILURE';
        $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $payload)->assertStatus(401);
        $this->assertSame(0, User::where('email', 'sam@example.com')->count());

        // A verified webhook is accepted and processed.
        $this->webhookVerificationStatus = 'SUCCESS';
        $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $payload)->assertStatus(200);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $user = User::where('email', 'sam@example.com')->firstOrFail();
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());

        // A webhook retry for the same event is idempotent.
        $this->call('POST', '/webhooks/paypal', [], [], [], $headers, $payload)->assertStatus(200);

        $this->assertSame(1, User::where('email', 'sam@example.com')->count());
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }
}
