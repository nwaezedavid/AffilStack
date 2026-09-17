<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Services\Referrals\ReferralPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Audit gap #5: a mixed-currency affiliate used to only ever be able to
 * request a payout in whichever currency had the largest unattached
 * balance — any other currency's commission just sat there until a later
 * request happened to make it the largest. This covers requesting each
 * currency independently instead.
 */
class MultiCurrencyPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function referrerWithPayoutMethod(): User
    {
        return User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
    }

    public function test_unpaid_commission_is_broken_down_by_currency(): void
    {
        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 6000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 4000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 2500]);

        $breakdown = $referrer->unpaidApprovedCommissionByCurrency();

        $this->assertSame(10000, $breakdown['USD']);
        $this->assertSame(2500, $breakdown['EUR']);
    }

    public function test_a_payout_can_be_requested_for_a_specific_currency(): void
    {
        config(['referrals.minimum_payout_cents' => 2000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);
        $eurEvent = ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 2500]);

        $payout = app(ReferralPayoutService::class)->requestPayout($referrer, 'EUR');

        $this->assertSame('EUR', $payout->currency);
        $this->assertSame(2500, $payout->amount_cents);
        $this->assertSame($payout->id, $eurEvent->fresh()->referral_payout_id);
    }

    public function test_requesting_one_currency_does_not_block_requesting_another(): void
    {
        config(['referrals.minimum_payout_cents' => 2000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 2500]);

        $service = app(ReferralPayoutService::class);
        $service->requestPayout($referrer, 'USD');

        // Should not raise — the open USD request must not block EUR.
        $eurPayout = $service->requestPayout($referrer, 'EUR');

        $this->assertSame('EUR', $eurPayout->currency);
        $this->assertSame(2, $referrer->referralPayouts()->count());
    }

    public function test_a_second_request_for_the_same_currency_while_one_is_open_is_rejected(): void
    {
        config(['referrals.minimum_payout_cents' => 2000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);

        $service = app(ReferralPayoutService::class);
        $service->requestPayout($referrer, 'USD');

        $this->expectException(RuntimeException::class);

        $service->requestPayout($referrer, 'USD');
    }

    public function test_the_minimum_payout_threshold_applies_independently_per_currency(): void
    {
        config(['referrals.minimum_payout_cents' => 5000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 1000]);

        $service = app(ReferralPayoutService::class);

        // Plenty of USD — succeeds.
        $service->requestPayout($referrer, 'USD');

        // EUR balance is below the minimum on its own — rejected.
        $this->expectException(InvalidArgumentException::class);

        $service->requestPayout($referrer, 'EUR');
    }

    public function test_the_referrals_page_offers_a_separate_request_button_per_currency(): void
    {
        config(['referrals.minimum_payout_cents' => 2000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 2500]);

        $response = $this->actingAs($referrer)->get(route('referrals.index'));

        $response->assertOk();
        $response->assertSee('Request USD 90.00', false);
        $response->assertSee('Request EUR 25.00', false);
    }

    public function test_the_referrals_page_shows_an_open_request_badge_for_a_currency_that_has_a_new_unclaimed_balance_while_one_is_pending(): void
    {
        config(['referrals.minimum_payout_cents' => 2000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 2500]);

        app(ReferralPayoutService::class)->requestPayout($referrer, 'USD');

        // A fresh USD commission approved after the request was made — it
        // must not be swept into a second, simultaneous USD request.
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 3000]);

        $response = $this->actingAs($referrer)->get(route('referrals.index'));

        $response->assertOk();
        $response->assertSee('Request processing');
        $response->assertSee('Request EUR 25.00', false);
        $response->assertDontSee('Request USD 30.00', false);
    }

    public function test_requesting_a_payout_from_the_dashboard_accepts_an_explicit_currency(): void
    {
        config(['referrals.minimum_payout_cents' => 2000]);

        $referrer = $this->referrerWithPayoutMethod();
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'USD', 'amount_cents' => 9000]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'currency' => 'EUR', 'amount_cents' => 2500]);

        $this->actingAs($referrer)
            ->post(route('referrals.payout'), ['currency' => 'EUR'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payout = $referrer->referralPayouts()->sole();
        $this->assertSame('EUR', $payout->currency);
        $this->assertSame(2500, $payout->amount_cents);
    }
}
