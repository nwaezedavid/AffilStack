<?php

namespace Tests\Feature;

use App\Models\CreditLedger;
use App\Models\CreditPackage;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Users should be able to buy more credit tokens if their monthly
 * allocation finishes" — a one-time purchase through
 * PaymentGateway::initiateOneTimeCheckout(), reusing the exact same
 * billing.callback/PaymentProcessor::process() machinery as a plan
 * subscription (see PaymentProcessor::grantCreditTopup()).
 */
class CreditTopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true]);
        config([
            'services.flutterwave.secret_key' => 'flw_test_fake',
            'services.stripe.secret_key' => 'sk_test_fake',
            'services.paystack.secret_key' => 'sk_test_fake',
            'services.paystack.usd_to_ngn_rate' => 1600,
            'services.paypal.client_id' => 'client_fake',
            'services.paypal.client_secret' => 'secret_fake',
        ]);
    }

    protected function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_the_index_page_lists_active_packages_only(): void
    {
        CreditPackage::factory()->create(['name' => 'Visible', 'is_active' => true, 'sort_order' => 1]);
        CreditPackage::factory()->create(['name' => 'Hidden', 'is_active' => false, 'sort_order' => 2]);

        $response = $this->actingAs($this->makeUser())->get(route('credit-topups.index'));

        $response->assertSuccessful();
        $response->assertSee('Visible');
        $response->assertDontSee('Hidden');
    }

    public function test_flutterwave_checkout_creates_a_pending_credit_topup_transaction(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 200, 'price_cents' => 1900]);

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/topup']], 200)]);

        $this->actingAs($user)->post(route('credit-topups.checkout', $package), ['gateway' => 'flutterwave'])
            ->assertRedirect('https://checkout.flutterwave.com/topup');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();
        $this->assertSame('credit_topup', $transaction->type);
        $this->assertSame($package->id, $transaction->credit_package_id);
        $this->assertSame(1900, $transaction->amount_cents);
        $this->assertSame('pending', $transaction->status);

        Http::assertSent(fn ($request) => data_get($request->data(), 'meta.type') === 'credit_topup'
            && data_get($request->data(), 'meta.credit_package_id') == $package->id);
    }

    public function test_completing_a_flutterwave_topup_grants_credits_and_flashes_a_confirmation(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 200, 'price_cents' => 1900]);
        $startingBalance = $user->credits_balance;

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/topup']], 200)]);

        $this->actingAs($user)->post(route('credit-topups.checkout', $package), ['gateway' => 'flutterwave']);

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();

        Http::fake(['api.flutterwave.com/v3/transactions/*/verify' => Http::response(['status' => 'success', 'data' => [
            'id' => 555111, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful',
            'amount' => 19, 'currency' => 'USD', 'customer' => ['email' => $user->email],
            'meta' => ['tx_ref' => $transaction->tx_ref, 'user_id' => (string) $user->id, 'credit_package_id' => (string) $package->id, 'type' => 'credit_topup'],
        ]], 200)]);

        $this->get(route('billing.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => '555111', 'status' => 'successful']))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('success', '200 credits have been added to your account.');

        $this->assertSame($startingBalance + 200, $user->fresh()->credits_balance);
        $this->assertSame(1, CreditLedger::where('user_id', $user->id)->where('reason', 'credit_topup_purchase')->count());
        $this->assertSame('successful', $transaction->fresh()->status);
    }

    public function test_stripe_one_time_checkout_uses_payment_mode_not_subscription(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 600, 'price_cents' => 4900]);

        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_topup_1', 'url' => 'https://checkout.stripe.com/pay/cs_topup_1'], 200)]);

        $this->actingAs($user)->post(route('credit-topups.checkout', $package), ['gateway' => 'stripe'])
            ->assertRedirect('https://checkout.stripe.com/pay/cs_topup_1');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/checkout/sessions')
                && $request['mode'] === 'payment'
                && ! isset($request['line_items'][0]['price_data']['recurring']);
        });
    }

    public function test_paystack_one_time_checkout_converts_to_naira(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 200, 'price_cents' => 1900]);

        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/topup'],
        ], 200)]);

        $this->actingAs($user)->withSession(['checkout_country' => 'NG'])
            ->post(route('credit-topups.checkout', $package), ['gateway' => 'paystack'])
            ->assertRedirect('https://checkout.paystack.com/topup');

        $transaction = PaymentTransaction::where('user_id', $user->id)->latest()->firstOrFail();
        $this->assertSame('NGN', $transaction->currency);
        $this->assertSame((int) round(19 * 1600 * 100), $transaction->amount_cents);
    }

    public function test_paypal_one_time_checkout_creates_a_capture_intent_order(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['credits' => 1500, 'price_cents' => 10900]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'A21AAtesttoken', 'expires_in' => 32400], 200);
            }

            if (str_ends_with($url, '/v2/checkout/orders') && $request->method() === 'POST') {
                return Http::response([
                    'id' => 'ORDERTOPUP', 'status' => 'CREATED',
                    'links' => [['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=ORDERTOPUP']],
                ], 201);
            }

            return Http::response([], 404);
        });

        $this->actingAs($user)->post(route('credit-topups.checkout', $package), ['gateway' => 'paypal'])
            ->assertRedirect('https://www.paypal.com/checkoutnow?token=ORDERTOPUP');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/checkout/orders')
            && $request['intent'] === 'CAPTURE');
    }

    public function test_an_inactive_package_cannot_be_purchased(): void
    {
        $user = $this->makeUser();
        $package = CreditPackage::factory()->create(['is_active' => false]);

        Http::fake();

        $this->actingAs($user)->post(route('credit-topups.checkout', $package), ['gateway' => 'flutterwave'])
            ->assertRedirect(route('credit-topups.index'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_credit_packages_are_admin_editable_and_public_list_is_cached(): void
    {
        $package = CreditPackage::factory()->create(['credits' => 200, 'is_active' => true]);

        $first = CreditPackage::activePublicList();
        $this->assertCount(1, $first);

        $package->update(['credits' => 999]);

        $this->assertSame(999, CreditPackage::activePublicList()->first()->credits);
    }
}
