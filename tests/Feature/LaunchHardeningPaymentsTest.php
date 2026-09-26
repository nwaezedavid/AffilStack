<?php

namespace Tests\Feature;

use App\Models\ApiWalletTransaction;
use App\Models\CreditLedger;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\RefundProcessor;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression tests for the pre-launch money-flow review (Sept 2026): each
 * test pins one loophole that existed before the fix.
 */
class LaunchHardeningPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        foreach (['flutterwave' => true, 'stripe' => true, 'paystack' => false, 'paypal' => false] as $gateway => $enabled) {
            PaymentGatewaySetting::create(['gateway' => $gateway, 'is_enabled' => $enabled]);
        }
    }

    protected function makePlan(string $slug = 'growth', int $monthlyCents = 6000, int $credits = 700): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'description' => 'Test plan',
            'price_monthly_cents' => $monthlyCents, 'price_yearly_cents' => Plan::yearlyPriceCentsFor($monthlyCents),
            'currency' => 'USD', 'credits_per_month' => $credits, 'active_products_limit' => 5,
            'contact_limit' => 5000, 'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function subscribedUser(Plan $plan, string $gateway = 'flutterwave', array $overrides = []): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Subscription::create(array_merge([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => $gateway,
            'current_period_start' => now()->subDay(), 'current_period_end' => now()->addDays(29),
        ], $overrides));

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paidResult(PaymentTransaction $transaction, array $overrides = []): array
    {
        return array_merge([
            'tx_ref' => $transaction->tx_ref,
            'remote_id' => 'remote_'.$transaction->id,
            'status' => 'successful',
            'amount' => $transaction->amount_cents / 100,
            'currency' => $transaction->currency,
            'meta' => [],
            'raw' => [],
        ], $overrides);
    }

    public function test_a_refunded_payment_is_not_granted_again_when_its_callback_is_replayed(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'subscription', 'gateway' => 'paypal', 'tx_ref' => 'tx_refund_replay',
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount_cents' => 6000, 'currency' => 'USD', 'status' => 'pending',
        ]);

        app(PaymentProcessor::class)->process('paypal', $this->paidResult($transaction));
        $this->assertSame(700, $user->fresh()->credits_balance);

        app(RefundProcessor::class)->process('paypal', [
            'kind' => 'refunded', 'gateway_tx_ids' => ['remote_'.$transaction->id], 'raw' => [],
        ]);

        // PayPal still reports the order COMPLETED after a refund — replay it.
        app(PaymentProcessor::class)->process('paypal', $this->paidResult($transaction));

        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->assertSame(0, $user->fresh()->credits_balance);
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->where('reason', 'monthly_grant')->count());
    }

    public function test_the_plan_comes_from_the_server_recorded_transaction_not_gateway_metadata(): void
    {
        $cheap = $this->makePlan('starter', 2700, 150);
        $expensive = $this->makePlan('agency', 49700, 7000);
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'subscription', 'gateway' => 'flutterwave', 'tx_ref' => 'tx_meta_forgery',
            'plan_id' => $cheap->id, 'billing_cycle' => 'monthly', 'amount_cents' => 2700, 'currency' => 'USD', 'status' => 'pending',
        ]);

        app(PaymentProcessor::class)->process('flutterwave', $this->paidResult($transaction, [
            'meta' => ['plan_id' => $expensive->id, 'billing_cycle' => 'yearly'],
        ]));

        $subscription = $user->fresh()->activeSubscription;
        $this->assertSame($cheap->id, $subscription->plan_id);
        $this->assertSame('monthly', $subscription->billing_cycle);
        $this->assertSame(150, $user->fresh()->credits_balance);
    }

    public function test_a_naira_api_wallet_top_up_credits_its_dollar_value_not_the_kobo_amount(): void
    {
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'api_wallet_topup', 'gateway' => 'paystack', 'tx_ref' => 'tx_ngn_wallet',
            'amount_cents' => 1_600_000, 'currency' => 'NGN', 'credited_amount_cents' => 1000, 'status' => 'pending',
        ]);

        app(PaymentProcessor::class)->process('paystack', $this->paidResult($transaction));

        $this->assertSame(1000, $user->fresh()->api_wallet_balance_cents);
    }

    public function test_a_foreign_currency_wallet_top_up_with_no_recorded_value_is_not_credited(): void
    {
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'api_wallet_topup', 'gateway' => 'paystack', 'tx_ref' => 'tx_ngn_legacy',
            'amount_cents' => 1_600_000, 'currency' => 'NGN', 'status' => 'pending',
        ]);

        app(PaymentProcessor::class)->process('paystack', $this->paidResult($transaction));

        $this->assertSame(0, $user->fresh()->api_wallet_balance_cents);
    }

    public function test_processing_the_same_payment_twice_grants_it_once(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'subscription', 'gateway' => 'flutterwave', 'tx_ref' => 'tx_twice',
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount_cents' => 6000, 'currency' => 'USD', 'status' => 'pending',
        ]);

        app(PaymentProcessor::class)->process('flutterwave', $this->paidResult($transaction));
        app(PaymentProcessor::class)->process('flutterwave', $this->paidResult($transaction));

        $this->assertSame(700, $user->fresh()->credits_balance);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }

    public function test_re_checking_out_the_current_plan_is_a_full_price_renewal_from_the_period_end(): void
    {
        $plan = $this->makePlan();
        $periodEnd = now()->addDays(29)->startOfSecond();
        $user = $this->subscribedUser($plan, overrides: ['current_period_end' => $periodEnd]);

        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/renew']], 200)]);

        $this->actingAs($user)->post(route('billing.checkout', $plan), ['billing_cycle' => 'monthly', 'gateway' => 'flutterwave'])
            ->assertRedirect('https://checkout.flutterwave.com/renew');

        // Full price — previously this was "price minus unused time" (about
        // $2 on day one) while still granting a whole month of credits.
        $transaction = PaymentTransaction::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(6000, $transaction->amount_cents);

        app(PaymentProcessor::class)->process('flutterwave', $this->paidResult($transaction));

        $subscription = $user->fresh()->activeSubscription;
        $this->assertTrue($subscription->current_period_start->equalTo($periodEnd));
        $this->assertTrue($subscription->current_period_end->equalTo($periodEnd->copy()->addMonth()));
    }

    public function test_an_upgrade_paid_through_stripe_never_gets_a_proration_discount(): void
    {
        $old = $this->makePlan('growth', 6000);
        $new = $this->makePlan('pro', 15000);
        $user = $this->subscribedUser($old, 'flutterwave');

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/pay/cs_1'], 200)]);

        $this->actingAs($user)->post(route('billing.checkout', $new), ['billing_cycle' => 'monthly', 'gateway' => 'stripe'])
            ->assertRedirect('https://checkout.stripe.com/pay/cs_1');

        $this->assertSame(15000, PaymentTransaction::where('user_id', $user->id)->latest('id')->value('amount_cents'));
    }

    public function test_a_fully_covered_upgrade_converts_banked_value_into_time_instead_of_keeping_a_yearly_period(): void
    {
        $growth = $this->makePlan('growth', 6000);
        $agency = $this->makePlan('agency', 19700, 7000);
        $user = $this->subscribedUser($growth, overrides: [
            'billing_cycle' => 'yearly',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addDays(364),
        ]);

        $this->actingAs($user)->post(route('billing.checkout', $agency), ['billing_cycle' => 'monthly', 'gateway' => 'flutterwave'])
            ->assertRedirect(route('billing.index'));

        $subscription = $user->fresh()->activeSubscription;
        $this->assertSame($agency->id, $subscription->plan_id);
        // ~$600 of banked yearly value buys ~3 months of a $197 plan, not 12.
        $this->assertLessThan(now()->addDays(100)->timestamp, $subscription->current_period_end->timestamp);
        $this->assertGreaterThan(now()->addDays(29)->timestamp, $subscription->current_period_end->timestamp);
    }

    public function test_the_signup_callback_only_logs_in_the_browser_that_started_the_signup(): void
    {
        $plan = $this->makePlan('starter', 2700, 150);
        $pending = PendingSignup::create([
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => bcrypt('secret-password'),
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'tx_ref' => 'affilstack_login_link',
            'status' => 'pending', 'expires_at' => now()->addDay(),
        ]);
        PaymentTransaction::create([
            'pending_signup_id' => $pending->id, 'type' => 'signup', 'gateway' => 'flutterwave', 'tx_ref' => $pending->tx_ref,
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'amount_cents' => 2700, 'currency' => 'USD', 'status' => 'pending',
        ]);

        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => [
            'id' => 777, 'tx_ref' => $pending->tx_ref, 'status' => 'successful', 'amount' => 27.00, 'currency' => 'USD',
            'customer' => ['email' => 'ada@example.com'],
            'meta' => ['pending_signup_id' => $pending->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
        ]], 200)]);

        // Someone else replaying the (guessable) callback URL gets a login
        // page, not the new customer's account.
        $this->get(route('registration.callback', ['gateway' => 'flutterwave', 'transaction_id' => 777, 'tx_ref' => $pending->tx_ref, 'status' => 'successful']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotNull(User::where('email', 'ada@example.com')->first());
    }

    public function test_a_gateway_chargeback_on_an_api_wallet_top_up_takes_the_balance_back(): void
    {
        $user = User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'api_wallet_topup', 'gateway' => 'stripe', 'tx_ref' => 'tx_wallet_dispute',
            'amount_cents' => 5000, 'currency' => 'USD', 'credited_amount_cents' => 5000, 'status' => 'pending',
        ]);
        app(PaymentProcessor::class)->process('stripe', $this->paidResult($transaction, ['remote_id' => 'pi_dispute']));
        $this->assertSame(5000, $user->fresh()->api_wallet_balance_cents);

        app(RefundProcessor::class)->process('stripe', ['kind' => 'charged_back', 'gateway_tx_ids' => ['pi_dispute'], 'raw' => []]);

        $this->assertSame(0, $user->fresh()->api_wallet_balance_cents);
        $this->assertTrue(ApiWalletTransaction::where('user_id', $user->id)->where('type', 'reversal')->exists());
    }

    public function test_replacing_a_stripe_subscription_cancels_the_old_one_at_stripe(): void
    {
        $old = $this->makePlan('growth', 6000);
        $new = $this->makePlan('pro', 15000);
        $user = $this->subscribedUser($old, 'stripe', ['gateway_subscription_id' => 'sub_old']);
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'type' => 'subscription', 'gateway' => 'stripe', 'tx_ref' => 'tx_upgrade_stripe',
            'plan_id' => $new->id, 'billing_cycle' => 'monthly', 'amount_cents' => 15000, 'currency' => 'USD', 'status' => 'pending',
        ]);

        Http::fake(['api.stripe.com/v1/subscriptions/sub_old' => Http::response(['id' => 'sub_old', 'status' => 'canceled'], 200)]);

        app(PaymentProcessor::class)->process('stripe', $this->paidResult($transaction, ['subscription_reference' => 'sub_new']));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/subscriptions/sub_old'));
        $this->assertSame('sub_new', $user->fresh()->activeSubscription->gateway_subscription_id);
    }
}
