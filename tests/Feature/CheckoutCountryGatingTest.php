<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\Plan;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit item #3: Paystack (NGN) is shown only to a checkout resolved as
 * Nigerian; Flutterwave/Stripe/PayPal (USD) only to everyone else — each
 * still gated by its own admin on/off toggle underneath that. See
 * CheckoutCountryResolver/PaymentGatewayManager::enabledForCountry().
 */
class CheckoutCountryGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_resolver_defaults_to_a_non_nigerian_result_for_a_private_test_ip(): void
    {
        $resolver = app(CheckoutCountryResolver::class);
        $request = Request::create('/pricing', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $this->assertSame('US', $resolver->resolve($request));
        $this->assertFalse($resolver->isNigeria($request));
    }

    public function test_resolver_uses_ip_geolocation_for_a_public_address(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'NG'], 200),
        ]);

        $resolver = app(CheckoutCountryResolver::class);
        $request = Request::create('/pricing', 'GET', server: ['REMOTE_ADDR' => '105.112.10.5']);

        $this->assertTrue($resolver->isNigeria($request));
    }

    public function test_resolver_falls_back_to_us_when_geolocation_fails(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([], 500),
        ]);

        $resolver = app(CheckoutCountryResolver::class);
        $request = Request::create('/pricing', 'GET', server: ['REMOTE_ADDR' => '105.112.10.5']);

        $this->assertSame('US', $resolver->resolve($request));
    }

    public function test_session_override_wins_over_geolocation(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'countryCode' => 'US'], 200),
        ]);

        $resolver = app(CheckoutCountryResolver::class);
        $request = Request::create('/pricing', 'GET', server: ['REMOTE_ADDR' => '105.112.10.5']);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('checkout_country', 'NG');

        $this->assertTrue($resolver->isNigeria($request));
    }

    public function test_a_nigerian_checkout_can_pay_in_naira_or_dollars_when_paystack_is_on(): void
    {
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        $plan = $this->makePlan();

        $this->withSession(['checkout_country' => 'NG'])
            ->get(route('registration.form', $plan))
            ->assertOk()
            ->assertSeeInOrder(['Pay in Naira (₦) — Paystack', 'Pay in US dollars ($) — Flutterwave'])
            ->assertSee('Not in Nigeria? Hide the Naira option');
    }

    public function test_a_nigerian_checkout_pays_in_dollars_with_flutterwave_while_paystack_is_off(): void
    {
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => false]);
        $plan = $this->makePlan();

        $this->withSession(['checkout_country' => 'NG'])
            ->get(route('registration.form', $plan))
            ->assertOk()
            ->assertSee('value="flutterwave"', false)
            ->assertDontSee('Paystack')
            // Naira isn't on offer at all, so there's nothing to switch to.
            ->assertDontSee('Naira option');
    }

    public function test_a_nigerian_signup_reaches_flutterwave_when_it_is_the_only_gateway(): void
    {
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        $plan = $this->makePlan();
        Http::fake([
            'api.flutterwave.com/*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/test']]),
        ]);

        $this->assertSame(
            ['flutterwave'],
            array_map(fn ($g) => $g->key(), app(PaymentGatewayManager::class)->enabledForCountry('NG')),
        );

        $this->withSession(['checkout_country' => 'NG'])
            ->post(route('registration.store', $plan), [
                'name' => 'Ada', 'email' => 'ada@example.com',
                'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple',
                'billing_cycle' => 'monthly',
                'gateway' => 'flutterwave',
                'accepts_refund_policy' => '1',
            ])
            ->assertSessionMissing('error')
            ->assertRedirect('https://checkout.flutterwave.com/pay/test');
    }

    public function test_signup_page_hides_paystack_when_the_country_override_is_not_nigeria(): void
    {
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paypal', 'is_enabled' => true]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        $plan = $this->makePlan();

        $this->withSession(['checkout_country' => 'US'])
            ->get(route('registration.form', $plan))
            ->assertOk()
            ->assertDontSee('Pay in Naira')
            ->assertSee('Pay in US dollars ($) — Stripe')
            ->assertSee('Pay in US dollars ($) — Flutterwave')
            ->assertSee('Pay in US dollars ($) — PayPal')
            ->assertSee('Paying from Nigeria? Show the Naira option');
    }

    public function test_a_non_nigerian_checkout_never_falls_back_to_paystack(): void
    {
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => false]);
        PaymentGatewaySetting::create(['gateway' => 'paystack', 'is_enabled' => true]);
        $plan = $this->makePlan();

        $response = $this->withSession(['checkout_country' => 'US'])
            ->post(route('registration.store', $plan), [
                'name' => 'Jane', 'email' => 'jane@example.com',
                'password' => 'correct-horse-battery-staple', 'password_confirmation' => 'correct-horse-battery-staple',
                'billing_cycle' => 'monthly',
                'gateway' => 'paystack',
                'accepts_refund_policy' => '1',
            ]);

        // Paystack charges in naira only, so with no dollar gateway on,
        // checkout can't proceed — the admin's own switches always win.
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_the_country_toggle_link_flips_the_session_override(): void
    {
        $this->post(route('checkout.country.update'), ['country' => 'NG'])->assertRedirect();
        $this->assertSame('NG', session('checkout_country'));

        $this->post(route('checkout.country.update'), ['country' => 'US'])->assertRedirect();
        $this->assertSame('US', session('checkout_country'));
    }
}
