<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\RefundProcessor;
use App\Services\Payments\StripeGateway;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regression tests for the pre-relaunch payments review (October 2026):
 * signups that are paid after the form was resubmitted, upgrade discounts
 * redeemed more than once, and Stripe disputes on renewal charges.
 */
class PaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        Notification::fake();
    }

    protected function plan(): Plan
    {
        return Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'x',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_paying_an_earlier_checkout_after_resubmitting_the_signup_form_still_creates_the_account(): void
    {
        $plan = $this->plan();
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://flw/pay']], 200)]);

        $form = [
            'name' => 'Jane', 'email' => 'jane@example.com',
            'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly', 'accepts_refund_policy' => '1',
        ];
        $this->post(route('registration.store', $plan), $form);
        $first = PaymentTransaction::where('type', 'signup')->firstOrFail();

        // Second submit (back button / second tab / attacker knowing the email).
        $this->post(route('registration.store', $plan), $form);

        // Customer completes payment on the FIRST checkout page.
        Http::fake(['api.flutterwave.com/v3/transactions/*' => Http::response(['status' => 'success', 'data' => [
            'id' => 111, 'tx_ref' => $first->tx_ref, 'status' => 'successful', 'amount' => 67.00, 'currency' => 'USD',
            'customer' => ['email' => 'jane@example.com'],
        ]], 200)]);
        config(['services.flutterwave.secret_hash' => 'h']);
        $this->postJson('/webhooks/flutterwave', ['event' => 'charge.completed', 'data' => ['id' => 111]], ['verif-hash' => 'h'])->assertOk();

        $first->refresh();
        $this->assertSame('subscription', $first->type);
        $this->assertNotNull($first->user_id);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_an_upgrade_discount_can_only_be_redeemed_once(): void
    {
        $old = Plan::create(['name' => 'A', 'slug' => 'a', 'description' => 'x', 'price_monthly_cents' => 9000, 'price_yearly_cents' => 90000,
            'currency' => 'USD', 'credits_per_month' => 100, 'active_products_limit' => 5, 'contact_limit' => 5000, 'team_seats' => 1,
            'channels' => ['research'], 'is_active' => true, 'sort_order' => 1]);
        $new = Plan::create(['name' => 'B', 'slug' => 'b', 'description' => 'x', 'price_monthly_cents' => 10000, 'price_yearly_cents' => 100000,
            'currency' => 'USD', 'credits_per_month' => 1000, 'active_products_limit' => 5, 'contact_limit' => 5000, 'team_seats' => 1,
            'channels' => ['research'], 'is_active' => true, 'sort_order' => 2]);
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::create(['user_id' => $user->id, 'plan_id' => $old->id, 'status' => 'active', 'billing_cycle' => 'monthly',
            'gateway' => 'flutterwave', 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addDays(29)]);

        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://flw/pay']], 200)]);
        foreach (range(1, 4) as $i) {
            $this->actingAs($user)->post(route('billing.checkout', $new), ['billing_cycle' => 'monthly', 'gateway' => 'flutterwave'])->assertRedirect('https://flw/pay');
        }
        $txs = PaymentTransaction::where('user_id', $user->id)->where('status', 'pending')->orderBy('id')->get();
        $paid = 0;
        foreach ($txs as $tx) {
            $paid += $tx->amount_cents;
            app(PaymentProcessor::class)->process('flutterwave', [
                'tx_ref' => $tx->tx_ref, 'remote_id' => 'r'.$tx->id, 'status' => 'successful',
                'amount' => $tx->amount_cents / 100, 'currency' => 'USD', 'meta' => [], 'raw' => [],
            ]);
        }
        $sub = $user->fresh()->activeSubscription;
        $this->assertSame($new->id, $sub->plan_id);
        $this->assertLessThanOrEqual(31, (int) now()->diffInDays($sub->current_period_end));
        $this->assertSame(1, PaymentTransaction::where('user_id', $user->id)->where('status', 'successful')->count());
        $this->assertSame(3, PaymentTransaction::where('user_id', $user->id)->where('status', 'needs_review')->count());
    }

    public function test_a_stripe_dispute_on_a_renewal_revokes_the_subscription(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly',
            'gateway' => 'stripe', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        PaymentTransaction::create([
            'user_id' => $user->id, 'subscription_id' => $sub->id, 'type' => 'renewal', 'gateway' => 'stripe',
            'gateway_tx_id' => 'in_123', 'gateway_reference' => 'pi_999', 'tx_ref' => 'renewal_x', 'amount_cents' => 6700, 'currency' => 'USD',
            'status' => 'successful', 'processed_at' => now(),
        ]);

        $request = Request::create('/webhooks/stripe', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'type' => 'charge.dispute.created',
            'data' => ['object' => ['id' => 'dp_1', 'payment_intent' => 'pi_999', 'amount' => 6700, 'currency' => 'usd', 'charge' => 'ch_1']],
        ]));
        $event = app(StripeGateway::class)->resolveRefundEvent($request);

        $this->assertNotNull(app(RefundProcessor::class)->process('stripe', $event));
        $this->assertNotSame('active', $sub->fresh()->status);
    }
}
