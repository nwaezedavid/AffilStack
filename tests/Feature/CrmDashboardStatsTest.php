<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRM/email dashboard (task #90): the pipeline stats header on /crm.
 */
class CrmDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_pipeline_counts_and_this_months_email_stats(): void
    {
        $this->seed(RolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $otherUser = User::factory()->create();

        CrmContact::create(['user_id' => $user->id, 'name' => 'A', 'source' => 'manual', 'status' => 'new']);
        CrmContact::create(['user_id' => $user->id, 'name' => 'B', 'source' => 'manual', 'status' => 'new']);
        CrmContact::create(['user_id' => $user->id, 'name' => 'C', 'source' => 'manual', 'status' => 'contacted']);
        CrmContact::create(['user_id' => $user->id, 'name' => 'D', 'source' => 'manual', 'status' => 'customer']);
        // Belongs to someone else — must never bleed into this user's counts.
        CrmContact::create(['user_id' => $otherUser->id, 'name' => 'Other', 'source' => 'manual', 'status' => 'new']);

        $contact = CrmContact::where('name', 'A')->first();
        CrmEmailSend::create([
            'user_id' => $user->id, 'crm_contact_id' => $contact->id, 'subject' => 'Hi', 'body' => 'Hi',
            'status' => 'sent', 'tracking_token' => 'tok1', 'sent_at' => now(), 'opened_at' => now(),
        ]);
        CrmEmailSend::create([
            'user_id' => $user->id, 'crm_contact_id' => $contact->id, 'subject' => 'Hi again', 'body' => 'Hi',
            'status' => 'sent', 'tracking_token' => 'tok2', 'sent_at' => now(),
        ]);
        // Last month — must not count toward "this month". created_at isn't
        // mass-assignable, so it's backdated via a direct property set
        // (unguarded) + save() after creation, not through create().
        $oldSend = CrmEmailSend::create([
            'user_id' => $user->id, 'crm_contact_id' => $contact->id, 'subject' => 'Old', 'body' => 'Hi',
            'status' => 'sent', 'tracking_token' => 'tok3', 'sent_at' => now()->subMonthNoOverflow(),
        ]);
        $oldSend->created_at = now()->startOfMonth()->subDay();
        $oldSend->save();

        $response = $this->actingAs($user)->get(route('crm.index'));

        $response->assertOk();
        $response->assertSeeText('Total contacts');
        $response->assertSeeText('4');
        $response->assertSeeText('2 emails sent this month');
        $response->assertSeeText('50% open rate this month');
    }

    public function test_no_open_rate_is_shown_when_nothing_was_sent_this_month(): void
    {
        $this->seed(RolesSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get(route('crm.index'));

        $response->assertOk();
        $response->assertSeeText('0 emails sent this month');
        $response->assertDontSeeText('open rate this month');
    }
}
