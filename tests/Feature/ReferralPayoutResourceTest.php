<?php

namespace Tests\Feature;

use App\Filament\Resources\ReferralPayouts\Pages\ListReferralPayouts;
use App\Filament\Resources\ReferralPayouts\ReferralPayoutResource;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Notifications\ReferralPayoutProcessed;
use App\Notifications\ReferralPayoutRejected;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin side of the payout workflow. "Paid" is unreachable except
 * through the "Mark paid" action — see ReferralPayoutServiceTest for the
 * service-level guarantees this UI relies on.
 */
class ReferralPayoutResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_payout_paid_through_the_ui_requires_a_reference_and_notifies_the_affiliate(): void
    {
        NotificationFacade::fake();
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $referrer = User::factory()->create();
        $payout = ReferralPayout::factory()->create(['user_id' => $referrer->id, 'status' => 'requested']);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        $event = ReferralEvent::factory()->approved()->create([
            'referral_id' => $referral->id,
            'referral_payout_id' => $payout->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListReferralPayouts::class)
            ->callTableAction('markPaid', $payout, data: ['reference' => 'PP-REF-999']);

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('PP-REF-999', $payout->reference);
        $this->assertSame('paid', $event->fresh()->status);

        NotificationFacade::assertSentTo($referrer, ReferralPayoutProcessed::class);
    }

    public function test_rejecting_a_payout_through_the_ui_releases_events_and_notifies_the_affiliate(): void
    {
        NotificationFacade::fake();
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $referrer = User::factory()->create();
        $payout = ReferralPayout::factory()->create(['user_id' => $referrer->id, 'status' => 'requested']);
        $referral = Referral::factory()->create(['referrer_id' => $referrer->id]);
        $event = ReferralEvent::factory()->approved()->create([
            'referral_id' => $referral->id,
            'referral_payout_id' => $payout->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListReferralPayouts::class)
            ->callTableAction('rejectPayout', $payout, data: ['note' => 'Details did not match our records']);

        $payout->refresh();
        $this->assertSame('rejected', $payout->status);
        $event->refresh();
        $this->assertSame('approved', $event->status);
        $this->assertNull($event->referral_payout_id);

        NotificationFacade::assertSentTo($referrer, ReferralPayoutRejected::class);
    }

    public function test_mark_paid_and_reject_actions_are_hidden_once_a_payout_is_no_longer_requested(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $paidPayout = ReferralPayout::factory()->create(['status' => 'paid']);
        $rejectedPayout = ReferralPayout::factory()->create(['status' => 'rejected']);

        Livewire::actingAs($admin)
            ->test(ListReferralPayouts::class)
            ->assertTableActionHidden('markPaid', $paidPayout)
            ->assertTableActionHidden('rejectPayout', $paidPayout)
            ->assertTableActionHidden('markPaid', $rejectedPayout)
            ->assertTableActionHidden('rejectPayout', $rejectedPayout);
    }

    public function test_a_department_scoped_sub_account_without_billing_access_cannot_reach_the_resource(): void
    {
        $this->seed(RolesSeeder::class);
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(ReferralPayoutResource::canAccess());

        $subAccount->syncPermissions(['department.billing']);
        $this->assertTrue(ReferralPayoutResource::canAccess());
    }
}
