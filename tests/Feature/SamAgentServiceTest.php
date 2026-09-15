<?php

namespace Tests\Feature;

use App\Models\CannedReply;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\TicketReplied;
use App\Notifications\TicketStatusChanged;
use App\Notifications\WelcomeAboard;
use App\Services\Agents\SamAgentService;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sam, the Support Agent (AI agents phase, agent #2): onboarding, ticket
 * progress notifications, canned-reply usage tracking, and the
 * template-suggestion learning loop.
 */
class SamAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_a_new_user_sends_a_welcome_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        app(SamAgentService::class)->onboardNewUser($user);

        Notification::assertSentTo($user, WelcomeAboard::class);
    }

    public function test_a_staff_reply_notifies_the_ticket_owner_but_a_customer_reply_does_not(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $customer->id, 'subject' => 'Billing question', 'category' => 'billing',
            'priority' => 'normal', 'status' => 'open', 'last_reply_at' => now(),
        ]);

        $ticket->messages()->create(['user_id' => $customer->id, 'is_staff' => false, 'message' => 'Why was I charged twice?']);
        Notification::assertNothingSent();

        $ticket->messages()->create(['user_id' => $staff->id, 'is_staff' => true, 'message' => 'Looking into this now.']);
        Notification::assertSentTo($customer, TicketReplied::class);
    }

    public function test_a_staff_member_replying_to_their_own_ticket_does_not_self_notify(): void
    {
        Notification::fake();
        $staffCustomer = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $staffCustomer->id, 'subject' => 'My own ticket', 'category' => 'other',
            'priority' => 'normal', 'status' => 'open', 'last_reply_at' => now(),
        ]);

        $ticket->messages()->create(['user_id' => $staffCustomer->id, 'is_staff' => true, 'message' => 'Following up myself.']);

        Notification::assertNothingSent();
    }

    public function test_a_ticket_newly_becoming_resolved_or_closed_notifies_the_customer_once_per_transition(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $customer->id, 'subject' => 'App not loading', 'category' => 'technical',
            'priority' => 'high', 'status' => 'open', 'last_reply_at' => now(),
        ]);

        $ticket->update(['status' => 'pending']);
        Notification::assertNothingSent();

        $ticket->update(['status' => 'resolved']);
        Notification::assertSentTimes(TicketStatusChanged::class, 1);

        // Direct resolved -> closed housekeeping: the customer already got
        // the resolved notice, nothing materially changed for them.
        $ticket->update(['status' => 'closed']);
        Notification::assertSentTimes(TicketStatusChanged::class, 1);

        // Reopened (a customer reply would do this) then closed again is a
        // genuinely fresh transition and does notify again.
        $ticket->update(['status' => 'open']);
        $ticket->update(['status' => 'closed']);
        Notification::assertSentTimes(TicketStatusChanged::class, 2);
    }

    public function test_recording_canned_reply_usage_bumps_the_counter_and_timestamp(): void
    {
        $reply = CannedReply::factory()->create(['usage_count' => 2, 'last_used_at' => now()->subWeek()]);

        app(SamAgentService::class)->recordCannedReplyUsage($reply);

        $reply->refresh();
        $this->assertSame(3, $reply->usage_count);
        $this->assertTrue($reply->last_used_at->isToday());
    }

    public function test_recording_usage_for_a_null_reply_does_nothing(): void
    {
        // Guards the "no template was actually selected" path in the
        // reply-picker's afterStateUpdated callback.
        app(SamAgentService::class)->recordCannedReplyUsage(null);

        $this->assertDatabaseCount('canned_replies', 0);
    }

    public function test_suggesting_templates_does_nothing_below_the_minimum_sample_size(): void
    {
        $ticket = $this->resolvedTicketWithStaffMessages(3);

        $created = app(SamAgentService::class)->suggestTemplates();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('canned_replies', 0);
    }

    public function test_suggesting_templates_creates_ai_drafted_suggestions_awaiting_review(): void
    {
        $this->resolvedTicketWithStaffMessages(10);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return ['suggestions' => [
                    ['title' => 'Refund timing', 'body' => 'Refunds take 5-7 business days to appear.', 'rationale' => 'Came up repeatedly on billing tickets.'],
                ]];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });

        $created = app(SamAgentService::class)->suggestTemplates();

        $this->assertSame(1, $created);
        $suggestion = CannedReply::where('title', 'Refund timing')->firstOrFail();
        $this->assertSame(CannedReply::STATUS_SUGGESTED, $suggestion->status);
        $this->assertSame(CannedReply::SOURCE_AI_SUGGESTED, $suggestion->source);
        $this->assertSame('Came up repeatedly on billing tickets.', $suggestion->ai_rationale);
    }

    public function test_suggesting_templates_skips_a_title_that_already_exists(): void
    {
        $this->resolvedTicketWithStaffMessages(10);
        CannedReply::factory()->create(['title' => 'Refund Timing']);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return ['suggestions' => [
                    ['title' => 'refund timing', 'body' => 'Duplicate of an existing template.', 'rationale' => 'n/a'],
                ]];
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });

        $created = app(SamAgentService::class)->suggestTemplates();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('canned_replies', 1);
    }

    public function test_suggesting_templates_fails_gracefully_when_ai_generation_errors(): void
    {
        $this->resolvedTicketWithStaffMessages(10);

        $this->app->instance(AIProvider::class, new class implements AIProvider
        {
            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return '';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                throw new AIGenerationException('provider unavailable');
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });

        $created = app(SamAgentService::class)->suggestTemplates();

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('canned_replies', 0);
    }

    protected function resolvedTicketWithStaffMessages(int $count): SupportTicket
    {
        $customer = User::factory()->create();
        $staff = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id' => $customer->id, 'subject' => 'Refund question', 'category' => 'billing',
            'priority' => 'normal', 'status' => 'resolved', 'last_reply_at' => now(),
        ]);

        for ($i = 0; $i < $count; $i++) {
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $staff->id,
                'is_staff' => true,
                'message' => "Refunds take 5-7 business days to appear back on your card (#{$i}).",
            ]);
        }

        return $ticket;
    }
}
