<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\CreditManager;
use App\Services\Payments\RefundEligibilityService;
use App\Services\Payments\RefundExecutionService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Audit item #8's refund policy — a single, strict, non-discretionary rule
 * (paid within the last 48 hours AND zero credits spent) with no admin
 * approval step, because an automatic objective rule can't be talked into
 * bending the way a human approver could. See RefundEligibilityService and
 * RefundExecutionService.
 */
class RefundPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true, 'credentials' => ['secret_key' => 'flw_test_fake']]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true, 'credentials' => ['secret_key' => 'sk_test_fake']]);
    }

    protected function makeSubscribedUser(): array
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);

        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'gateway' => 'flutterwave',
            'gateway_tx_id' => '998877',
            'gateway_reference' => 'flw_ref_998877',
            'tx_ref' => 'affilstack_growth_monthly_test',
            'amount_cents' => 6700,
            'currency' => 'USD',
            'status' => 'successful',
            'processed_at' => now(),
        ]);

        app(CreditManager::class)->grant($user, $plan->credits_per_month, 'monthly_grant', $subscription);

        return [$user, $subscription, $transaction];
    }

    public function test_a_recent_payment_with_zero_usage_is_eligible(): void
    {
        [$user, , $transaction] = $this->makeSubscribedUser();

        $eligible = app(RefundEligibilityService::class)->eligibleTransaction($user);

        $this->assertNotNull($eligible);
        $this->assertTrue($transaction->is($eligible));
    }

    public function test_a_payment_older_than_48_hours_is_not_eligible(): void
    {
        [$user, , $transaction] = $this->makeSubscribedUser();
        $transaction->update(['processed_at' => now()->subHours(49)]);

        $this->assertNull(app(RefundEligibilityService::class)->eligibleTransaction($user));
    }

    public function test_any_spent_credit_makes_the_account_permanently_ineligible(): void
    {
        [$user] = $this->makeSubscribedUser();

        app(CreditManager::class)->spend($user, 1, 'ai_generation');

        $this->assertNull(app(RefundEligibilityService::class)->eligibleTransaction($user));
    }

    public function test_requesting_a_refund_issues_the_gateway_refund_cancels_the_subscription_and_claws_back_credits(): void
    {
        [$user, $subscription, $transaction] = $this->makeSubscribedUser();

        Http::fake([
            'api.flutterwave.com/v3/transactions/998877/refund' => Http::response(['status' => 'success', 'data' => ['id' => 'refund_1']], 200),
        ]);

        $refund = app(RefundExecutionService::class)->request($user);

        $this->assertTrue($refund->wasRefunded());
        $this->assertSame($transaction->id, $refund->payment_transaction_id);
        $this->assertSame('flutterwave', $refund->gateway);
        $this->assertSame(6700, $refund->amount_cents);

        $transaction->refresh();
        $this->assertSame('refunded', $transaction->status);

        $subscription->refresh();
        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);

        // Eligibility guaranteed zero usage, so the full 600 credits granted
        // for this subscription are clawed back — balance returns to 0.
        $this->assertSame(0, app(CreditManager::class)->balance($user));
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $user->id,
            'amount' => -600,
            'reason' => 'refund_clawback',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/transactions/998877/refund'));
    }

    public function test_requesting_a_refund_when_ineligible_throws(): void
    {
        [$user, , $transaction] = $this->makeSubscribedUser();
        $transaction->update(['processed_at' => now()->subHours(72)]);

        $this->expectException(InvalidArgumentException::class);

        app(RefundExecutionService::class)->request($user);
    }

    public function test_a_failed_gateway_refund_records_an_ineligible_request_and_leaves_the_transaction_untouched(): void
    {
        [$user, $subscription, $transaction] = $this->makeSubscribedUser();

        Http::fake([
            'api.flutterwave.com/v3/transactions/998877/refund' => Http::response(['status' => 'error', 'message' => 'Transaction not refundable'], 400),
        ]);

        $refund = app(RefundExecutionService::class)->request($user);

        $this->assertFalse($refund->wasRefunded());
        $this->assertSame('ineligible', $refund->status);
        $this->assertSame('Transaction not refundable', $refund->reason);

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);

        $this->assertSame(600, app(CreditManager::class)->balance($user));
    }

    public function test_paystack_refund_uses_the_gateway_reference(): void
    {
        [$user, , $transaction] = $this->makeSubscribedUser();
        $transaction->update([
            'gateway' => 'paystack', 'gateway_tx_id' => '112233', 'gateway_reference' => 'affilstack_growth_monthly_ps',
        ]);

        Http::fake([
            'api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['transaction_reference' => 'affilstack_growth_monthly_ps']], 200),
        ]);

        $refund = app(RefundExecutionService::class)->request($user);

        $this->assertTrue($refund->wasRefunded());
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/refund'
            && $request['transaction'] === 'affilstack_growth_monthly_ps');
    }

    public function test_billing_page_shows_the_refund_button_and_requesting_it_redirects_with_success(): void
    {
        [$user] = $this->makeSubscribedUser();

        Http::fake([
            'api.flutterwave.com/v3/transactions/998877/refund' => Http::response(['status' => 'success', 'data' => ['id' => 'refund_1']], 200),
        ]);

        $this->actingAs($user)->get(route('billing.index'))->assertSee('Request refund');

        $this->actingAs($user)->post(route('billing.request-refund'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('refund_requests', ['user_id' => $user->id, 'status' => 'refunded']);
    }

    public function test_billing_page_hides_the_refund_button_once_ineligible(): void
    {
        [$user, , $transaction] = $this->makeSubscribedUser();
        $transaction->update(['processed_at' => now()->subHours(72)]);

        $this->actingAs($user)->get(route('billing.index'))->assertDontSee('Request refund');
    }

    public function test_signup_requires_accepting_the_refund_policy(): void
    {
        $plan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'description' => 'Test plan',
            'price_monthly_cents' => 1900, 'price_yearly_cents' => 19000, 'currency' => 'USD',
            'credits_per_month' => 200, 'active_products_limit' => 1, 'contact_limit' => 500,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->post(route('registration.store', $plan), [
            'name' => 'Jane', 'email' => 'jane-no-policy@example.com',
            'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple',
            'billing_cycle' => 'monthly',
        ])->assertSessionHasErrors('accepts_refund_policy');

        $this->assertDatabaseMissing('pending_signups', ['email' => 'jane-no-policy@example.com']);
    }
}
