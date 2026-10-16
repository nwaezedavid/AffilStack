<?php

namespace Tests\Feature;

use App\Filament\Resources\ReferralPayouts\Pages\ListReferralPayouts;
use App\Models\Addon;
use App\Models\AddonInstall;
use App\Models\DeveloperEarning;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Models\UserTemplate;
use App\Notifications\AddonSubscriptionEnded;
use App\Notifications\AddonSubscriptionPaymentFailed;
use App\Services\Marketplace\DeveloperPayouts;
use App\Services\Payments\RefundProcessor;
use App\Services\Referrals\ReferralPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Support\MarketplaceTestHelpers;
use Tests\TestCase;

/**
 * Feature 7: paid add-ons are monthly subscriptions billed by AffilStack;
 * developers get 70% of each payment, paid out with the partner payouts.
 */
class AddonBillingTest extends TestCase
{
    use MarketplaceTestHelpers;
    use RefreshDatabase;

    protected User $developerUser;

    protected Addon $pack;

    protected string $checkoutLink = 'https://checkout.flutterwave.com/addon';

    /** @var array{0: array<string, mixed>, 1: int} */
    protected array $stripeCharge = [['id' => 'pi_renew', 'status' => 'succeeded'], 200];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMarketplaceRoles();
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        config(['services.flutterwave.secret_key' => 'flw_test_fake', 'services.stripe.secret_key' => 'sk_test_fake', 'referrals.minimum_payout_cents' => 500]);

        // One fake for the whole test: the checkout link, the verification of
        // the newest pending add-on payment, and saved-card charges.
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'api.flutterwave.com/v3/payments')) {
                return Http::response(['status' => 'success', 'data' => ['link' => $this->checkoutLink]], 200);
            }

            if (str_contains($url, 'api.flutterwave.com/v3/transactions/')) {
                $transaction = PaymentTransaction::where('type', 'addon_subscription')->where('status', 'pending')->latest('id')->firstOrFail();

                return Http::response(['status' => 'success', 'data' => [
                    'id' => 777000 + $transaction->id, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful', 'amount' => $transaction->amount_cents / 100, 'currency' => 'USD',
                    'customer' => ['email' => $transaction->user->email],
                    'meta' => ['tx_ref' => $transaction->tx_ref, 'type' => 'addon_subscription'],
                ]], 200);
            }

            if (str_contains($url, 'api.stripe.com/v1/payment_intents')) {
                return Http::response(...$this->stripeCharge);
            }

            return Http::response([], 404);
        });

        $this->developerUser = $this->developer();
        $this->pack = $this->liveAddon($this->developerUser, 'template_pack', ['name' => 'Hook Vault', 'pricing' => 'monthly', 'price_cents' => 1000]);
    }

    /**
     * Subscribes through the Flutterwave checkout and its callback.
     */
    protected function subscribe(User $customer): AddonInstall
    {
        $this->actingAs($customer)->post(route('marketplace.subscribe', $this->pack), ['gateway' => 'flutterwave'])
            ->assertRedirect($this->checkoutLink);

        $transaction = PaymentTransaction::where('user_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('addon_subscription', $transaction->type);
        $this->assertSame(1000, $transaction->amount_cents);
        $this->assertSame('pending', $transaction->status);

        $install = $transaction->addonInstall;
        $remoteId = (string) (777000 + $transaction->id);

        $this->actingAs($customer)->get(route('billing.callback', ['gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => $remoteId, 'status' => 'successful']))
            ->assertRedirect(route('addons.show', $install))
            ->assertSessionHas('success');

        return $install->fresh();
    }

    protected function savedCard(User $customer, string $token = 'pm_saved'): PaymentMethod
    {
        return PaymentMethod::create(['user_id' => $customer->id, 'gateway' => 'stripe', 'type' => 'card', 'brand' => 'visa', 'last4' => '4242', 'gateway_token' => $token, 'gateway_customer_id' => 'cus_saved', 'is_default' => true]);
    }

    public function test_subscribing_activates_the_add_on_for_a_month_and_records_the_developers_share(): void
    {
        $customer = $this->customer();
        $install = $this->subscribe($customer);

        $this->assertSame('active', $install->status);
        $this->assertSame(1000, $install->price_cents);
        $this->assertTrue($install->current_period_end->between(now()->addDays(27), now()->addDays(32)));
        $this->assertSame(2, UserTemplate::where('user_id', $customer->id)->count());
        $this->assertSame(1, $this->pack->fresh()->installs_count);

        $earning = DeveloperEarning::sole();
        $this->assertSame(1000, $earning->gross_cents);
        $this->assertSame(700, $earning->amount_cents);
        $this->assertSame('USD', $earning->currency);
        $this->assertSame('pending', $earning->status);
        $this->assertTrue($earning->available_at->isSameDay(now()->addDays(14)));

        $this->actingAs($customer)->get(route('addons.show', $install))->assertOk()->assertSee('$10.00')->assertSee('Cancel subscription');
        $this->actingAs($customer)->post(route('marketplace.subscribe', $this->pack), ['gateway' => 'flutterwave'])->assertSessionHas('error', 'You already have this add-on.');
    }

    public function test_renewal_charges_the_saved_card_and_extends_the_month(): void
    {
        $customer = $this->customer();
        $install = $this->subscribe($customer);
        $install->update(['current_period_end' => now()->addHours(3), 'payment_method_id' => $this->savedCard($customer)->id]);

        $this->artisan('marketplace:renew')->assertSuccessful();

        $install->refresh();
        $this->assertSame('active', $install->status);
        $this->assertTrue($install->current_period_end->greaterThan(now()->addDays(27)));
        $this->assertDatabaseHas('payment_transactions', ['addon_install_id' => $install->id, 'type' => 'addon_subscription', 'gateway' => 'stripe', 'gateway_tx_id' => 'pi_renew', 'status' => 'successful']);
        $this->assertSame(2, DeveloperEarning::count());

        // Not charged twice in the same run window.
        $this->artisan('marketplace:renew');
        $this->assertSame(2, DeveloperEarning::count());
    }

    public function test_a_failed_renewal_gives_a_few_days_grace_then_ends_the_add_on(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $install = $this->subscribe($customer);
        $install->update(['current_period_end' => now()->addHour(), 'payment_method_id' => $this->savedCard($customer, 'pm_declined')->id]);

        $this->stripeCharge = [['error' => ['message' => 'Your card was declined.']], 402];

        $this->artisan('marketplace:renew');

        $this->assertSame('past_due', $install->fresh()->status);
        $this->assertTrue($install->fresh()->isUsable());
        Notification::assertSentTo($customer, AddonSubscriptionPaymentFailed::class);
        $this->actingAs($customer)->get(route('addons.show', $install))->assertSee('Pay now');

        $this->travel(4)->days();
        $this->artisan('marketplace:renew');

        $this->assertSame('expired', $install->fresh()->status);
        $this->assertSame(0, UserTemplate::where('user_id', $customer->id)->count());
        Notification::assertSentTo($customer, AddonSubscriptionEnded::class);
    }

    public function test_paying_now_after_a_failed_renewal_restores_the_subscription(): void
    {
        $customer = $this->customer();
        $install = $this->subscribe($customer);
        $install->update(['status' => 'past_due', 'current_period_end' => now()->subDay()]);

        $this->checkoutLink = 'https://checkout.flutterwave.com/pay-now';

        $this->actingAs($customer)->post(route('addons.pay', $install), ['gateway' => 'flutterwave'])->assertRedirect('https://checkout.flutterwave.com/pay-now');
        $this->assertSame(1000, PaymentTransaction::where('addon_install_id', $install->id)->latest('id')->first()->credited_amount_cents);
    }

    public function test_cancelling_keeps_the_add_on_until_the_end_of_the_month(): void
    {
        $customer = $this->customer();
        $install = $this->subscribe($customer);

        $this->actingAs($customer)->post(route('addons.cancel', $install))->assertRedirect(route('addons.show', $install));
        $this->assertTrue($install->fresh()->cancel_at_period_end);
        $this->assertSame('active', $install->fresh()->status);

        $this->actingAs($customer)->post(route('addons.resume', $install));
        $this->assertFalse($install->fresh()->cancel_at_period_end);

        $this->actingAs($customer)->post(route('addons.cancel', $install));
        $this->travel(32)->days();
        $this->artisan('marketplace:renew');

        $this->assertSame('expired', $install->fresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.stripe.com'));
    }

    public function test_a_refund_ends_the_add_on_and_takes_back_the_developers_share(): void
    {
        $customer = $this->customer();
        $install = $this->subscribe($customer);
        $transaction = PaymentTransaction::where('addon_install_id', $install->id)->sole();

        app(RefundProcessor::class)->process('flutterwave', ['kind' => 'refunded', 'gateway_tx_ids' => [(string) $transaction->gateway_tx_id], 'raw' => []]);

        $this->assertSame('refunded', $transaction->fresh()->status);
        $this->assertSame('expired', $install->fresh()->status);
        $this->assertSame('reversed', DeveloperEarning::sole()->status);
    }

    public function test_a_refund_after_payout_records_a_negative_earning(): void
    {
        $customer = $this->customer();
        $install = $this->subscribe($customer);
        $transaction = PaymentTransaction::where('addon_install_id', $install->id)->sole();
        DeveloperEarning::query()->update(['status' => 'paid']);

        app(RefundProcessor::class)->process('flutterwave', ['kind' => 'charged_back', 'gateway_tx_ids' => [(string) $transaction->gateway_tx_id], 'raw' => []]);

        $negative = DeveloperEarning::where('type', 'chargeback')->sole();
        $this->assertSame(-700, $negative->amount_cents);
        $this->assertSame('approved', $negative->status);
    }

    public function test_a_developer_requests_a_payout_once_earnings_pass_the_refund_window(): void
    {
        $this->subscribe($this->customer());
        $this->subscribe($this->customer());
        $developer = $this->developerUser->fresh();
        $developer->update(['payout_method' => 'paypal', 'payout_details' => ['paypal_email' => 'dev@acme.example']]);
        $payouts = app(DeveloperPayouts::class);

        $this->assertSame(['available' => 0, 'pending' => 1400, 'paid' => 0, 'currency' => 'USD'], $payouts->balance($developer->developerProfile));

        try {
            $payouts->requestPayout($developer);
            $this->fail('A payout was allowed before the refund window passed.');
        } catch (InvalidArgumentException) {
        }

        $this->travel(15)->days();
        $this->actingAs($developer)->get(route('developer.earnings'))->assertOk()->assertSee('$14.00');

        $this->actingAs($developer)->post(route('developer.payout.request'))->assertSessionHas('success');

        $payout = ReferralPayout::sole();
        $this->assertSame('developer', $payout->source);
        $this->assertSame(1400, $payout->amount_cents);
        $this->assertSame('USD', $payout->currency);
        $this->assertSame(2, $payout->developerEarnings()->count());
        $this->assertTrue($developer->hasOpenPayoutRequest('USD', 'developer'));
        $this->assertFalse($developer->hasOpenPayoutRequest('USD'));

        // Shown to admins with the partner payouts, and paid the same way.
        Livewire::actingAs($this->admin())->test(ListReferralPayouts::class)->assertSee('Add-on earnings');
        app(ReferralPayoutService::class)->processPayout($payout, $this->admin(), 'PAYPAL-123');

        $this->assertSame(['paid'], DeveloperEarning::pluck('status')->unique()->values()->all());
        $this->assertSame(1400, $payouts->balance($developer->developerProfile)['paid']);

        // Not on the customer's Refer & Earn page.
        $this->actingAs($developer)->get(route('referrals.index'))->assertDontSee('PAYPAL-123');
    }

    public function test_a_rejected_developer_payout_releases_the_earnings(): void
    {
        $this->subscribe($this->customer());
        $developer = $this->developerUser->fresh();
        $developer->update(['payout_method' => 'paypal', 'payout_details' => ['paypal_email' => 'dev@acme.example']]);
        $this->travel(15)->days();

        $payout = app(DeveloperPayouts::class)->requestPayout($developer);
        app(ReferralPayoutService::class)->rejectPayout($payout, $this->admin(), 'Wrong PayPal address');

        $this->assertNull(DeveloperEarning::sole()->referral_payout_id);
        $this->assertSame('approved', DeveloperEarning::sole()->status);
    }
}
