<?php

namespace Tests\Feature;

use App\Console\Commands\Subscriptions\ApplyPendingDowngrades;
use App\Models\CreditLedger;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PlanChangeService;
use App\Services\Payments\SubscriptionRenewalService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Self-service plan upgrade/downgrade (audit item #2) — an upgrade charges
 * only the unused-time credit on the current plan (except Stripe, which
 * never gets a discount here — see PlanChangeService), a downgrade is
 * scheduled for the paid-for period's end and never touches a gateway.
 */
class PlanChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => false]);
    }

    protected function makePlan(string $slug, int $monthlyCents, int $credits = 500): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'description' => 'Test plan',
            'price_monthly_cents' => $monthlyCents, 'price_yearly_cents' => Plan::yearlyPriceCentsFor($monthlyCents),
            'currency' => 'USD', 'credits_per_month' => $credits, 'active_products_limit' => 5,
            'contact_limit' => 5000, 'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function makeUserWithSubscription(Plan $plan, string $gateway = 'flutterwave', array $overrides = []): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Subscription::create(array_merge([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'gateway' => $gateway,
            'current_period_start' => now()->subDays(10), 'current_period_end' => now()->addDays(20),
        ], $overrides));

        return $user;
    }

    public function test_upgrading_charges_only_the_prorated_difference(): void
    {
        $oldPlan = $this->makePlan('growth', 6000); // $60/mo
        $newPlan = $this->makePlan('pro', 15000); // $150/mo
        $user = $this->makeUserWithSubscription($oldPlan);

        // 20 of 30 days remaining ≈ 2/3 unused → credit ≈ $40.00 of the $60
        // already paid, capped under the new plan's $150 price.
        Http::fake(['api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/xyz']], 200)]);

        $this->actingAs($user)->post(route('billing.checkout', $newPlan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'flutterwave',
        ])->assertRedirect('https://checkout.flutterwave.com/xyz');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();
        $this->assertLessThan(15000, $transaction->amount_cents);
        $this->assertGreaterThan(0, $transaction->amount_cents);

        Http::assertSent(fn ($request) => $request['amount'] == $transaction->amount_cents / 100);
    }

    public function test_stripe_upgrades_never_get_a_proration_discount(): void
    {
        $oldPlan = $this->makePlan('growth', 6000);
        $newPlan = $this->makePlan('pro', 15000);
        $user = $this->makeUserWithSubscription($oldPlan, 'stripe');

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/pay/cs_test_1'], 200)]);

        $this->actingAs($user)->post(route('billing.checkout', $newPlan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'stripe',
        ])->assertRedirect('https://checkout.stripe.com/pay/cs_test_1');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();
        $this->assertSame(15000, $transaction->amount_cents);
    }

    public function test_downgrading_schedules_the_change_and_never_touches_a_gateway(): void
    {
        $oldPlan = $this->makePlan('pro', 15000);
        $newPlan = $this->makePlan('growth', 6000);
        $user = $this->makeUserWithSubscription($oldPlan);

        Http::fake();

        $response = $this->actingAs($user)->post(route('billing.checkout', $newPlan), [
            'billing_cycle' => 'monthly',
            'gateway' => 'flutterwave',
        ]);

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('success');
        Http::assertNothingSent();

        $subscription = $user->activeSubscription->fresh();
        $this->assertSame($newPlan->id, $subscription->pending_plan_id);
        $this->assertSame('monthly', $subscription->pending_billing_cycle);
        // The current (paid-for) plan keeps working until the period ends.
        $this->assertSame($oldPlan->id, $subscription->plan_id);
    }

    public function test_a_scheduled_downgrade_can_be_canceled(): void
    {
        $oldPlan = $this->makePlan('pro', 15000);
        $newPlan = $this->makePlan('growth', 6000);
        $user = $this->makeUserWithSubscription($oldPlan, overrides: ['pending_plan_id' => null]);
        $subscription = $user->activeSubscription;
        app(PlanChangeService::class)->scheduleDowngrade($subscription, $newPlan, 'monthly');

        $this->actingAs($user)->delete(route('billing.cancel-scheduled-change'))
            ->assertRedirect(route('billing.index'));

        $this->assertNull($subscription->fresh()->pending_plan_id);
        $this->assertSame($oldPlan->id, $subscription->fresh()->plan_id);
    }

    public function test_apply_pending_downgrades_command_applies_once_the_period_has_ended(): void
    {
        $oldPlan = $this->makePlan('pro', 15000, credits: 2000);
        $newPlan = $this->makePlan('growth', 6000, credits: 500);
        $user = $this->makeUserWithSubscription($oldPlan, overrides: [
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->subDay(),
        ]);
        $subscription = $user->activeSubscription;
        app(PlanChangeService::class)->scheduleDowngrade($subscription, $newPlan, 'monthly');

        $this->artisan(ApplyPendingDowngrades::class)->assertSuccessful();

        $subscription->refresh();
        $this->assertSame($newPlan->id, $subscription->plan_id);
        $this->assertNull($subscription->pending_plan_id);
        // Nothing was paid at period end on a one-time-charge gateway, so no
        // credits — the new plan's allowance comes with the next checkout.
        $this->assertFalse(CreditLedger::where('user_id', $user->id)->where('reason', 'plan_change_grant')->exists());
    }

    public function test_apply_pending_downgrades_command_ignores_stripe_and_unexpired_periods(): void
    {
        $oldPlan = $this->makePlan('pro', 15000);
        $newPlan = $this->makePlan('growth', 6000);

        // Stripe: left alone even though its period has "ended" — that
        // gateway's downgrade is applied at its own renewal webhook instead.
        $stripeUser = $this->makeUserWithSubscription($oldPlan, 'stripe', [
            'current_period_start' => now()->subMonth(), 'current_period_end' => now()->subDay(),
        ]);
        app(PlanChangeService::class)->scheduleDowngrade($stripeUser->activeSubscription, $newPlan, 'monthly');

        // Flutterwave, but the period hasn't ended yet.
        $activeUser = $this->makeUserWithSubscription($oldPlan, 'flutterwave', [
            'current_period_start' => now()->subDays(5), 'current_period_end' => now()->addDays(25),
        ]);
        app(PlanChangeService::class)->scheduleDowngrade($activeUser->activeSubscription, $newPlan, 'monthly');

        $this->artisan(ApplyPendingDowngrades::class)->assertSuccessful();

        $this->assertSame($oldPlan->id, $stripeUser->activeSubscription->fresh()->plan_id, 'Stripe plan should be untouched by the sweep');
        $this->assertNotNull($stripeUser->activeSubscription->fresh()->pending_plan_id, 'Stripe downgrade should not be applied by the sweep');
        $this->assertNotNull($activeUser->activeSubscription->fresh()->pending_plan_id, 'Unexpired period should not have its downgrade applied yet');
    }

    public function test_stripe_renewal_applies_a_pending_downgrade_instead_of_the_old_plans_grant(): void
    {
        $oldPlan = $this->makePlan('pro', 15000, credits: 2000);
        $newPlan = $this->makePlan('growth', 6000, credits: 500);
        $user = $this->makeUserWithSubscription($oldPlan, 'stripe', [
            'gateway_subscription_id' => 'sub_test_1',
        ]);
        $subscription = $user->activeSubscription;

        Http::fake([
            'api.stripe.com/v1/subscriptions/sub_test_1' => Http::sequence()
                ->push(['id' => 'sub_test_1', 'items' => ['data' => [['id' => 'si_1', 'price' => ['product' => 'prod_1']]]]])
                ->push(['id' => 'sub_test_1']),
        ]);

        app(PlanChangeService::class)->scheduleDowngrade($subscription, $newPlan, 'monthly');

        // The new price is set on Stripe itself (from the next invoice on,
        // no proration) — otherwise Stripe would keep billing the old plan.
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/subscriptions/sub_test_1')
            && $request['proration_behavior'] === 'none'
            && (int) $request['items'][0]['price_data']['unit_amount'] === 6000
            && $request['items'][0]['price_data']['product'] === 'prod_1');

        app(SubscriptionRenewalService::class)->handleStripeEvent([
            'kind' => 'renewed',
            'gateway_subscription_id' => 'sub_test_1',
            'gateway_tx_id' => 'in_test_1',
            'amount' => 60.00,
            'currency' => 'USD',
            'raw' => [],
        ]);

        $subscription->refresh();
        $this->assertSame($newPlan->id, $subscription->plan_id);
        $this->assertNull($subscription->pending_plan_id);
        $this->assertSame(500, CreditLedger::where('user_id', $user->id)->where('reason', 'plan_change_grant')->sole()->amount);
        $this->assertSame(0, CreditLedger::where('user_id', $user->id)->where('reason', 'renewal_grant')->count());
    }
}
