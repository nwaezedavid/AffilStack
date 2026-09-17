<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Notifications\ReferralPayoutProcessed;
use App\Notifications\ReferralPayoutRejected;
use App\Services\Referrals\ReferralPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * The referral program overhaul's core integrity rule: a ReferralEvent
 * only ever reaches "paid" by being cascaded from a processed
 * ReferralPayout — never a bare status flip. See ReferralPayoutService.
 */
class ReferralPayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_payout_fails_without_a_payout_method_on_file(): void
    {
        $referrer = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 10000]);

        $this->expectException(InvalidArgumentException::class);

        app(ReferralPayoutService::class)->requestPayout($referrer);
    }

    public function test_requesting_a_payout_fails_below_the_minimum_threshold(): void
    {
        config(['referrals.minimum_payout_cents' => 5000]);

        $referrer = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 1000]);

        $this->expectException(InvalidArgumentException::class);

        app(ReferralPayoutService::class)->requestPayout($referrer);
    }

    public function test_requesting_a_payout_fails_when_an_open_request_already_exists(): void
    {
        config(['referrals.minimum_payout_cents' => 5000]);

        $referrer = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 10000]);

        app(ReferralPayoutService::class)->requestPayout($referrer);

        $this->expectException(RuntimeException::class);

        app(ReferralPayoutService::class)->requestPayout($referrer);
    }

    public function test_requesting_a_payout_succeeds_and_attaches_the_approved_events(): void
    {
        config(['referrals.minimum_payout_cents' => 5000]);

        $referrer = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        $eventOne = ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 6000]);
        $eventTwo = ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 4000]);
        // Still pending — must not be swept into the payout.
        $pendingEvent = ReferralEvent::factory()->create(['referral_id' => $referral->id, 'amount_cents' => 9999]);

        $payout = app(ReferralPayoutService::class)->requestPayout($referrer);

        $this->assertSame(10000, $payout->amount_cents);
        $this->assertSame('USD', $payout->currency);
        $this->assertSame('requested', $payout->status);
        $this->assertSame('paypal', $payout->payout_method);
        $this->assertSame('affiliate@example.com', $payout->payout_details['paypal_email']);
        $this->assertSame($payout->id, $eventOne->fresh()->referral_payout_id);
        $this->assertSame($payout->id, $eventTwo->fresh()->referral_payout_id);
        $this->assertNull($pendingEvent->fresh()->referral_payout_id);
    }

    public function test_processing_a_payout_marks_it_paid_cascades_events_and_notifies_the_affiliate(): void
    {
        NotificationFacade::fake();
        config(['referrals.minimum_payout_cents' => 5000]);

        $admin = User::factory()->create();
        $referrer = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        $event = ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 10000]);

        $service = app(ReferralPayoutService::class);
        $payout = $service->requestPayout($referrer);

        $service->processPayout($payout, $admin, 'PP-REF-123', 'Sent manually');

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('PP-REF-123', $payout->reference);
        $this->assertSame('Sent manually', $payout->note);
        $this->assertSame($admin->id, $payout->processed_by_id);
        $this->assertNotNull($payout->processed_at);
        $this->assertSame('paid', $event->fresh()->status);

        NotificationFacade::assertSentTo($referrer, ReferralPayoutProcessed::class);
    }

    public function test_processing_a_payout_that_is_not_requested_is_rejected(): void
    {
        $admin = User::factory()->create();
        $payout = ReferralPayout::factory()->create(['status' => 'paid']);

        $this->expectException(InvalidArgumentException::class);

        app(ReferralPayoutService::class)->processPayout($payout, $admin, 'REF-1');
    }

    public function test_rejecting_a_payout_releases_events_back_to_approved_and_notifies_the_affiliate(): void
    {
        NotificationFacade::fake();
        config(['referrals.minimum_payout_cents' => 5000]);

        $admin = User::factory()->create();
        $referrer = User::factory()->create([
            'payout_method' => 'paypal',
            'payout_details' => ['paypal_email' => 'affiliate@example.com'],
        ]);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        $event = ReferralEvent::factory()->approved()->create(['referral_id' => $referral->id, 'amount_cents' => 10000]);

        $service = app(ReferralPayoutService::class);
        $payout = $service->requestPayout($referrer);

        $service->rejectPayout($payout, $admin, 'Bad PayPal email on file');

        $payout->refresh();
        $this->assertSame('rejected', $payout->status);
        $this->assertSame('Bad PayPal email on file', $payout->note);
        $this->assertSame($admin->id, $payout->processed_by_id);

        $event->refresh();
        $this->assertSame('approved', $event->status);
        $this->assertNull($event->referral_payout_id);

        NotificationFacade::assertSentTo($referrer, ReferralPayoutRejected::class);
    }
}
