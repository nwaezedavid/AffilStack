<?php

namespace Tests\Feature;

use App\Filament\Widgets\SupportInboxWidget;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin Dashboard Overview's support-inbox stat card. Regression test
 * for a real bug found while screenshotting the platform for the feature
 * guide: an open ticket with no reply yet (last_reply_at is null — nobody,
 * staff or customer, has replied since it was created) crashed the whole
 * admin dashboard with "Error while loading page", because the widget
 * called ->diffForHumans() straight on a null last_reply_at.
 */
class SupportInboxWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_crash_when_the_oldest_open_ticket_has_no_reply_yet(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->create();

        SupportTicket::create([
            'user_id' => $customer->id,
            'subject' => 'Nobody has replied to this yet',
            'category' => 'other',
            'priority' => 'normal',
            'status' => 'open',
            'last_reply_at' => null,
        ]);

        Livewire::actingAs($admin)->test(SupportInboxWidget::class)
            ->assertSuccessful()
            ->assertSee('Nobody has replied to this yet');
    }

    public function test_a_never_replied_ticket_still_counts_as_the_oldest_unanswered_using_its_created_at(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->create();

        $repliedRecently = SupportTicket::create([
            'user_id' => $customer->id, 'subject' => 'Replied an hour ago', 'category' => 'billing',
            'priority' => 'normal', 'status' => 'open', 'last_reply_at' => now()->subHour(),
        ]);

        $neverReplied = SupportTicket::create([
            'user_id' => $customer->id, 'subject' => 'Never replied, opened two days ago', 'category' => 'technical',
            'priority' => 'high', 'status' => 'open', 'last_reply_at' => null,
        ]);
        $neverReplied->forceFill(['created_at' => now()->subDays(2)])->save();

        // The never-replied ticket was created before the other one's last
        // reply, so it — not the recently-replied ticket — is the oldest
        // unanswered one, using its created_at as the effective wait-since time.
        Livewire::actingAs($admin)->test(SupportInboxWidget::class)
            ->assertSuccessful()
            ->assertSee('Never replied, opened two days ago')
            ->assertDontSee($repliedRecently->subject);
    }

    public function test_an_empty_inbox_shows_a_clean_state_instead_of_crashing(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)->test(SupportInboxWidget::class)
            ->assertSuccessful()
            ->assertSee('Inbox is clear');
    }
}
