<?php

namespace Tests\Feature;

use App\Models\CreditLedger;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionRenewalReminder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Task #85 — subscription renewal reliability, across both gateways:
 *  - Stripe auto-renews via webhook (StripeGateway::resolveRenewalEvent()
 *    + SubscriptionRenewalService::handleStripeEvent()).
 *  - Flutterwave never auto-renews, so it gets a reminder email before
 *    current_period_end, and expireLapsed() is the safety net (for either
 *    gateway) that actually revokes access once the date passes.
 */
class SubscriptionRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        config(['services.stripe.webhook_secret' => 'whsec_test_fake']);
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

    protected function postStripeWebhook(array $event): TestResponse
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_fake');

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_a_subscription_cycle_invoice_renews_the_subscription_and_grants_credits(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create(['credits_balance' => 0]);
        $user->assignRole('user');
        $subscription = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay(),
        ]);

        $response = $this->postStripeWebhook([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_test_1',
                'subscription' => 'sub_1',
                'billing_reason' => 'subscription_cycle',
                'amount_paid' => 6700,
                'currency' => 'usd',
            ]],
        ]);

        $response->assertStatus(200);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->current_period_end->isAfter(now()->addDays(25)));

        $this->assertDatabaseHas('payment_transactions', [
            'subscription_id' => $subscription->id, 'type' => 'renewal', 'gateway_tx_id' => 'in_test_1',
            'status' => 'successful', 'amount_cents' => 6700,
        ]);

        $this->assertSame(600, $user->fresh()->credits_balance);
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->where('reason', 'renewal_grant')->count());
    }

    public function test_the_initial_checkout_invoice_is_not_treated_as_a_renewal(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create(['credits_balance' => 0]);
        $subscription = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        $this->postStripeWebhook([
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_test_initial',
                'subscription' => 'sub_1',
                'billing_reason' => 'subscription_create',
                'amount_paid' => 6700,
                'currency' => 'usd',
            ]],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('payment_transactions', ['gateway_tx_id' => 'in_test_initial']);
        $this->assertSame(0, $user->fresh()->credits_balance);
    }

    public function test_a_retried_renewal_invoice_is_idempotent(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create(['credits_balance' => 0]);
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay(),
        ]);

        $event = [
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'id' => 'in_test_retry', 'subscription' => 'sub_1',
                'billing_reason' => 'subscription_cycle', 'amount_paid' => 6700, 'currency' => 'usd',
            ]],
        ];

        $this->postStripeWebhook($event)->assertStatus(200);
        $this->postStripeWebhook($event)->assertStatus(200);

        $this->assertSame(1, PaymentTransaction::where('gateway_tx_id', 'in_test_retry')->count());
        $this->assertSame(600, $user->fresh()->credits_balance);
    }

    public function test_a_failed_renewal_invoice_marks_the_subscription_past_due_and_notifies_the_user(): void
    {
        Notification::fake();

        $plan = $this->makePlan();
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay(),
        ]);

        $this->postStripeWebhook([
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_test_failed', 'subscription' => 'sub_1']],
        ])->assertStatus(200);

        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);
        $this->assertNull($user->fresh()->activeSubscription);

        Notification::assertSentTo($user, SubscriptionPaymentFailed::class);
    }

    public function test_a_deleted_stripe_subscription_is_marked_canceled(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe',
            'gateway_customer_id' => 'cus_1', 'gateway_subscription_id' => 'sub_1',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDay(),
        ]);

        $this->postStripeWebhook([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_1']],
        ])->assertStatus(200);

        $subscription->refresh();
        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_flutterwave_subscribers_get_one_reminder_before_their_period_ends(): void
    {
        Notification::fake();

        $plan = $this->makePlan();
        $dueSoon = User::factory()->create();
        $dueLater = User::factory()->create();

        $subscriptionDueSoon = Subscription::create([
            'user_id' => $dueSoon->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'flutterwave',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addDays(2),
        ]);
        Subscription::create([
            'user_id' => $dueLater->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'flutterwave',
            'current_period_start' => now(), 'current_period_end' => now()->addDays(20),
        ]);

        $this->artisan('subscriptions:send-renewal-reminders')->assertSuccessful();

        Notification::assertSentTo($dueSoon, SubscriptionRenewalReminder::class);
        Notification::assertNotSentTo($dueLater, SubscriptionRenewalReminder::class);
        $this->assertNotNull($subscriptionDueSoon->fresh()->renewal_reminder_sent_at);

        // Running it again the same day doesn't double-email.
        Notification::fake();
        $this->artisan('subscriptions:send-renewal-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_lapsed_subscriptions_of_either_gateway_expire_and_lose_access(): void
    {
        Notification::fake();

        $plan = $this->makePlan();
        $lapsedFlutterwave = User::factory()->create();
        $lapsedStripe = User::factory()->create();
        $stillActive = User::factory()->create();

        Subscription::create([
            'user_id' => $lapsedFlutterwave->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'flutterwave',
            'current_period_start' => now()->subMonths(2), 'current_period_end' => now()->subDay(),
        ]);
        Subscription::create([
            'user_id' => $lapsedStripe->id, 'plan_id' => $plan->id, 'status' => 'past_due',
            'billing_cycle' => 'monthly', 'gateway' => 'stripe', 'gateway_subscription_id' => 'sub_lapsed',
            'current_period_start' => now()->subMonths(2), 'current_period_end' => now()->subDays(3),
        ]);
        Subscription::create([
            'user_id' => $stillActive->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => 'flutterwave',
            'current_period_start' => now(), 'current_period_end' => now()->addDays(10),
        ]);

        $this->artisan('subscriptions:expire-lapsed')->assertSuccessful();

        $this->assertSame('expired', Subscription::where('user_id', $lapsedFlutterwave->id)->value('status'));
        $this->assertSame('expired', Subscription::where('user_id', $lapsedStripe->id)->value('status'));
        $this->assertSame('active', Subscription::where('user_id', $stillActive->id)->value('status'));

        $this->assertNull($lapsedFlutterwave->fresh()->activeSubscription);
        $this->assertNull($lapsedStripe->fresh()->activeSubscription);
        $this->assertNotNull($stillActive->fresh()->activeSubscription);

        Notification::assertSentTo($lapsedFlutterwave, SubscriptionExpired::class);
        Notification::assertSentTo($lapsedStripe, SubscriptionExpired::class);
        Notification::assertNotSentTo($stillActive, SubscriptionExpired::class);
    }
}
