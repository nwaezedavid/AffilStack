<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End to end: a visitor sees an affiliate's embedded banner on another
 * site, clicks it (→ /r/{code}, which sets the 60-day cookie), then buys a
 * plan — and the affiliate is credited with the sign-up and the commission
 * through the existing referral flow.
 */
class AffiliateBannerAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_purchase_after_clicking_an_embedded_banner_credits_the_affiliate(): void
    {
        Notification::fake();
        $this->seed(RolesSeeder::class);
        PaymentGatewaySetting::create(['gateway' => 'flutterwave', 'is_enabled' => true]);

        $plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth', 'description' => 'Test plan',
            'price_monthly_cents' => 6700, 'price_yearly_cents' => 67000, 'currency' => 'USD',
            'credits_per_month' => 600, 'active_products_limit' => 5, 'contact_limit' => 5000,
            'team_seats' => 1, 'channels' => ['research'], 'is_active' => true, 'sort_order' => 1,
        ]);
        $affiliate = User::factory()->create(['email' => 'partner@example.com']);
        $code = $affiliate->referralCode();

        // 1. The banner iframe, as rendered on a third-party site.
        $frame = $this->get("/embed/banner/300x250?ref={$code}&design=workflow", ['User-Agent' => 'Mozilla/5.0 Chrome/128.0']);
        $frame->assertOk();
        $this->assertSame([], $frame->headers->getCookies());
        preg_match('/href="([^"]+)"/', $frame->getContent(), $match);
        $clickUrl = html_entity_decode($match[1]);
        $this->assertStringStartsWith(url("/r/{$code}"), $clickUrl);

        // 2. The click: logged against the banner, and the 60-day cookie set.
        $click = $this->get($clickUrl);
        $click->assertRedirect();
        $cookie = collect($click->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('referrals.cookie_name'));
        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(now()->addDays(60)->timestamp, $cookie->getExpiresTime(), 5);
        $this->assertSame('workflow-300x250', $affiliate->referralClicks()->sole()->banner_key);

        // 3. Sign-up + payment with that cookie, through the real flow.
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/fake']], 200),
        ]);

        $this->withCookie(config('referrals.cookie_name'), $code)
            ->post(route('registration.store', $plan), [
                'name' => 'New Customer',
                'email' => 'customer@example.com',
                'password' => 'correct-horse-battery-staple',
                'password_confirmation' => 'correct-horse-battery-staple',
                'billing_cycle' => 'monthly',
                'accepts_refund_policy' => '1',
            ])
            ->assertRedirect('https://checkout.flutterwave.com/pay/fake');

        $pending = PendingSignup::where('email', 'customer@example.com')->firstOrFail();
        $this->assertSame($affiliate->id, $pending->referred_by_user_id);
        $transaction = PaymentTransaction::where('pending_signup_id', $pending->id)->firstOrFail();

        Http::fake([
            'api.flutterwave.com/v3/transactions/*' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 4455, 'tx_ref' => $transaction->tx_ref, 'status' => 'successful',
                    'amount' => 67.00, 'currency' => 'USD', 'customer' => ['email' => 'customer@example.com'],
                    'meta' => ['pending_signup_id' => $pending->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'],
                ],
            ], 200),
        ]);

        $this->get(route('registration.callback', [
            'gateway' => 'flutterwave', 'tx_ref' => $transaction->tx_ref, 'transaction_id' => 4455, 'status' => 'successful',
        ]))->assertRedirect(route('dashboard'));

        // 4. The affiliate is credited.
        $customer = User::where('email', 'customer@example.com')->firstOrFail();
        $referral = Referral::where('referred_user_id', $customer->id)->firstOrFail();
        $this->assertSame($affiliate->id, $referral->referrer_id);
        $this->assertSame('converted', $referral->status);

        $commission = $referral->events()->sole();
        $this->assertSame('first_payment', $commission->event_type);
        $this->assertSame((int) round($transaction->fresh()->amount_cents * config('referrals.commission_rate')), $commission->amount_cents);
        $this->assertSame('pending', $commission->status);
    }
}
