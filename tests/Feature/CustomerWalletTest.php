<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\CreditPackage;
use App\Models\CustomerWalletTransaction;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\Payments\SubscriptionRenewalService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * The customer wallet: top up through checkout, spend on credit packs and
 * plan renewals, and admin credits/refunds — a separate ledger from the
 * affiliate payout wallet and the API wallet.
 */
class CustomerWalletTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        config([
            'services.flutterwave.secret_key' => 'flw_test_fake',
            'services.paystack.secret_key' => 'sk_test_fake',
            'services.paystack.usd_to_ngn_rate' => 1600,
        ]);

        $this->plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    protected function makeUser(): User
    {
        $user = User::factory()->create(['credits_balance' => 0]);
        $user->assignRole('user');

        return $user;
    }

    protected function fund(User $user, int $amountMinor): void
    {
        app(CustomerWalletManager::class)->credit($user, $amountMinor, 'admin_credit', 'Test funding');
    }

    protected function savedPaystackCard(User $user): PaymentMethod
    {
        return PaymentMethod::create([
            'user_id' => $user->id, 'gateway' => 'paystack', 'type' => 'card', 'brand' => 'visa',
            'last4' => '4242', 'gateway_token' => 'AUTH_saved', 'is_default' => true,
        ]);
    }

    public function test_the_wallet_page_shows_balance_top_up_and_history(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 2550);

        $this->actingAs($user)->get(route('wallet.index'))
            ->assertSuccessful()
            ->assertSee('$25.50')
            ->assertSee('Top up')
            ->assertSee('Use wallet for renewals')
            ->assertSee('Test funding');
    }

    public function test_a_top_up_is_credited_once_only_after_the_payment_is_verified(): void
    {
        $user = $this->makeUser();

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/wallet']], 200)]);

        $this->actingAs($user)->post(route('wallet.top-up'), ['amount' => '40', 'gateway' => 'flutterwave'])
            ->assertRedirect('https://checkout.flutterwave.com/wallet');

        $transaction = PaymentTransaction::where('user_id', $user->id)->sole();
        $this->assertSame('customer_wallet_topup', $transaction->type);
        $this->assertSame(4000, $transaction->credited_amount_cents);
        $this->assertSame(0, app(CustomerWalletManager::class)->balance($user), 'Nothing is credited before verification.');

        Http::fake(['api.flutterwave.com/v3/transactions/*/verify' => Http::response(['status' => 'success', 'data' => [
            'id' => 777, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful',
            'amount' => 40, 'currency' => 'USD', 'customer' => ['email' => $user->email],
            'meta' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'type' => 'customer_wallet_topup'],
        ]], 200)]);

        $callback = route('billing.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => '777', 'status' => 'successful']);

        $this->get($callback)->assertRedirect(route('wallet.index'))->assertSessionHas('success', '$40.00 has been added to your wallet.');
        $this->get($callback);

        $this->assertSame(4000, app(CustomerWalletManager::class)->balance($user));
        $this->assertSame(1, CustomerWalletTransaction::where('user_id', $user->id)->where('type', 'topup')->count());
    }

    public function test_an_unverified_top_up_never_credits_the_wallet(): void
    {
        $user = $this->makeUser();

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/wallet']], 200)]);
        $this->actingAs($user)->post(route('wallet.top-up'), ['amount' => '40', 'gateway' => 'flutterwave']);
        $transaction = PaymentTransaction::where('user_id', $user->id)->sole();

        Http::fake(['api.flutterwave.com/v3/transactions/*/verify' => Http::response(['status' => 'success', 'data' => [
            'id' => 778, 'tx_ref' => $transaction->tx_ref, 'status' => 'failed',
            'amount' => 40, 'currency' => 'USD', 'customer' => ['email' => $user->email], 'meta' => ['tx_ref' => $transaction->tx_ref],
        ]], 200)]);

        $this->get(route('billing.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => '778', 'status' => 'failed']));

        $this->assertSame(0, app(CustomerWalletManager::class)->balance($user));
    }

    public function test_a_credit_pack_can_be_paid_from_the_wallet(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 200, 'price_cents' => 1900]);
        $this->fund($user, 2500);

        $this->actingAs($user)->get(route('credit-topups.index'))->assertSee('Pay from wallet');

        $this->actingAs($user)->post(route('credit-topups.wallet', $package))
            ->assertRedirect(route('billing.index'));

        $this->assertSame(600, app(CustomerWalletManager::class)->balance($user));
        $this->assertSame(200, $user->fresh()->credits_balance);
        $this->assertSame('successful', PaymentTransaction::where('user_id', $user->id)->where('type', 'credit_topup')->sole()->status);
        $this->assertSame(-1900, CustomerWalletTransaction::where('user_id', $user->id)->where('type', 'credit_pack')->sole()->amount_minor);
    }

    public function test_a_credit_pack_is_refused_when_the_wallet_is_short(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 200, 'price_cents' => 1900]);
        $this->fund($user, 1000);

        $this->actingAs($user)->post(route('credit-topups.wallet', $package))
            ->assertRedirect(route('credit-topups.index'))
            ->assertSessionHas('error');

        $this->assertSame(1000, app(CustomerWalletManager::class)->balance($user));
        $this->assertSame(0, $user->fresh()->credits_balance);
    }

    public function test_a_plan_can_be_paid_from_the_wallet(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 10000);

        $this->actingAs($user)->post(route('billing.checkout', $this->plan), ['billing_cycle' => 'monthly', 'pay_from_wallet' => 1])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('success');

        $subscription = $user->fresh()->activeSubscription;
        $this->assertSame($this->plan->id, $subscription->plan_id);
        $this->assertSame('wallet', $subscription->gateway);
        $this->assertSame(3300, app(CustomerWalletManager::class)->balance($user));
        $this->assertSame(600, $user->fresh()->credits_balance);
    }

    public function test_a_renewal_is_paid_from_the_wallet_first(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 10000);
        $subscription = $this->dueSubscription($user);

        $stats = app(SubscriptionRenewalService::class)->chargeDueRenewals();

        $this->assertSame(['renewed' => 1, 'failed' => 0], $stats);
        $this->assertSame(3300, app(CustomerWalletManager::class)->balance($user));
        $this->assertTrue($subscription->fresh()->current_period_end->greaterThan(now()->addMonth()->subDay()));
        $this->assertSame('wallet', PaymentTransaction::where('subscription_id', $subscription->id)->where('type', 'renewal')->sole()->gateway);
    }

    public function test_the_saved_card_covers_what_the_wallet_cannot(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 2000);
        $this->savedPaystackCard($user);
        $subscription = $this->dueSubscription($user);

        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => 'ref_renew']], 200)]);

        app(SubscriptionRenewalService::class)->chargeDueRenewals();

        $this->assertSame(0, app(CustomerWalletManager::class)->balance($user));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'charge_authorization') && $request['amount'] === (int) round(47.00 * 1600 * 100));
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('paystack', PaymentTransaction::where('subscription_id', $subscription->id)->where('type', 'renewal')->sole()->gateway);
    }

    public function test_turning_off_use_wallet_for_renewals_charges_the_card_in_full(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 10000);
        $this->savedPaystackCard($user);
        $subscription = $this->dueSubscription($user);

        $this->actingAs($user)->patch(route('wallet.settings'), ['use_for_renewals' => 0])->assertSessionHas('success');
        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'success', 'reference' => 'ref_full']], 200)]);

        app(SubscriptionRenewalService::class)->chargeDueRenewals();

        $this->assertSame(10000, app(CustomerWalletManager::class)->balance($user));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'charge_authorization') && $request['amount'] === (int) round(67.00 * 1600 * 100));
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_a_declined_card_never_takes_a_partial_payment_from_the_wallet(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 2000);
        $this->savedPaystackCard($user);
        $subscription = $this->dueSubscription($user);
        $periodEnd = $subscription->current_period_end;

        Http::fake(['api.paystack.co/transaction/charge_authorization' => Http::response(['status' => true, 'data' => ['status' => 'failed', 'gateway_response' => 'Declined']], 200)]);

        $stats = app(SubscriptionRenewalService::class)->chargeDueRenewals();

        $this->assertSame(1, $stats['failed']);
        $this->assertSame(2000, app(CustomerWalletManager::class)->balance($user));
        $this->assertTrue($subscription->fresh()->current_period_end->equalTo($periodEnd));
    }

    public function test_wallet_transactions_can_never_be_edited_or_deleted(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 500);
        $row = CustomerWalletTransaction::where('user_id', $user->id)->sole();

        try {
            $row->update(['amount_minor' => 99999]);
            $this->fail('An update should have been refused.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $row->delete();
    }

    public function test_admin_can_add_a_credit_or_refund_with_a_required_note(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);
        $user = $this->makeUser();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('adjustWallet', ['type' => 'admin_credit', 'amount' => '15', 'note' => ''])
            ->assertHasFormErrors(['note' => 'required']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('adjustWallet', ['type' => 'admin_refund', 'amount' => '15', 'note' => 'Refund for a failed video'])
            ->assertHasNoFormErrors();

        $row = CustomerWalletTransaction::where('user_id', $user->id)->sole();
        $this->assertSame('admin_refund', $row->type);
        $this->assertSame(1500, $row->amount_minor);
        $this->assertSame('Refund for a failed video', $row->note);
        $this->assertSame($admin->id, $row->created_by_user_id);
        $this->assertSame(1500, app(CustomerWalletManager::class)->balance($user));
    }

    public function test_the_balance_shows_on_the_billing_page_and_in_the_user_menu(): void
    {
        $user = $this->makeUser();
        $this->fund($user, 1234);

        $response = $this->actingAs($user)->get(route('billing.index'))->assertSuccessful();

        $this->assertSame(2, substr_count($response->getContent(), '$12.34'));
    }

    public function test_the_wallet_opens_in_the_customers_plan_currency(): void
    {
        $user = $this->makeUser();
        $nairaPlan = Plan::create([
            'name' => 'Naira', 'slug' => 'naira', 'description' => 'NGN plan',
            'price_monthly_cents' => 500000, 'price_yearly_cents' => 5000000, 'currency' => 'NGN',
            'credits_per_month' => 100, 'active_products_limit' => 1, 'contact_limit' => 100,
            'team_seats' => 1, 'channels' => [], 'is_active' => true, 'sort_order' => 2,
        ]);
        Subscription::create([
            'user_id' => $user->id, 'plan_id' => $nairaPlan->id, 'status' => 'active', 'billing_cycle' => 'monthly',
            'gateway' => 'paystack', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        $wallet = app(CustomerWalletManager::class)->walletFor($user);

        $this->assertSame('NGN', $wallet->currency);
        $this->assertSame('₦0.00', $wallet->formattedBalance());
        $this->assertSame('USD', app(CustomerWalletManager::class)->walletFor($this->makeUser())->currency);
    }

    protected function dueSubscription(User $user): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'status' => 'active', 'billing_cycle' => 'monthly',
            'gateway' => 'flutterwave', 'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addHours(12),
        ]);
    }
}
