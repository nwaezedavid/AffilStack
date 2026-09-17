<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The affiliate-facing side of the payout workflow — saving payout details
 * and requesting a payout from the dashboard. Service-level guarantees are
 * covered by ReferralPayoutServiceTest; this just checks the controller
 * wires user input to the service and surfaces its outcome.
 */
class ReferralPayoutDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_a_paypal_payout_method(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('referrals.payout-method'), [
                'payout_method' => 'paypal',
                'paypal_email' => 'me@example.com',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('paypal', $user->payout_method);
        $this->assertSame('me@example.com', $user->payout_details['paypal_email']);
    }

    public function test_a_user_can_save_a_bank_transfer_payout_method(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('referrals.payout-method'), [
                'payout_method' => 'bank_transfer',
                'bank_account_name' => 'Jane Doe',
                'bank_account_number' => '000123456',
                'bank_name' => 'First National',
                'bank_swift_or_routing' => 'ABCUS33',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('bank_transfer', $user->payout_method);
        $this->assertSame('First National', $user->payout_details['bank_name']);
    }

    public function test_saving_a_payout_method_requires_its_method_specific_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('referrals.payout-method'), [
                'payout_method' => 'paypal',
            ])
            ->assertSessionHasErrors('paypal_email');
    }

    public function test_a_user_can_request_a_payout_once_eligible(): void
    {
        config(['referrals.minimum_payout_cents' => 5000]);

        $user = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'me@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $user->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 10000]);

        $this->actingAs($user)
            ->post(route('referrals.payout'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, $user->referralPayouts()->count());
    }

    public function test_requesting_a_payout_below_threshold_shows_an_error_and_creates_nothing(): void
    {
        config(['referrals.minimum_payout_cents' => 5000]);

        $user = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'me@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $user->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 1000]);

        $this->actingAs($user)
            ->post(route('referrals.payout'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $user->referralPayouts()->count());
    }
}
