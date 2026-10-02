<?php

namespace Tests\Feature;

use App\Models\CrmCampaign;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\CrmReply;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\Crm\PersonalEmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\CrmTestHelpers;
use Tests\TestCase;

/**
 * The replies Inbox: conversations (unread first), the thread view, "Draft
 * reply with AI" (charged, using the research + campaign + thread) and a
 * threaded send on the user's own connection.
 */
class CrmInboxTest extends TestCase
{
    use CrmTestHelpers, RefreshDatabase;

    protected User $user;

    protected CrmContact $contact;

    protected CrmReply $reply;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->crmUser();
        $list = $this->listWithContacts($this->user, $this->offerFor($this->user), 1);
        $this->contact = $list->contacts()->first();
        $campaign = CrmCampaign::factory()->launched()->create(['user_id' => $this->user->id, 'crm_list_id' => $list->id, 'offer_id' => $list->offer_id, 'name' => 'Dentist launch']);
        $send = CrmEmailSend::create([
            'user_id' => $this->user->id, 'crm_contact_id' => $this->contact->id, 'kind' => 'campaign', 'crm_campaign_id' => $campaign->id,
            'subject' => 'Quick idea for Sunrise', 'body' => 'Hi Ada, our pitch.', 'status' => 'sent', 'sent_at' => now()->subDays(2),
            'tracking_token' => 'tok-x', 'message_id' => '<camp-x@mybiz.example>', 'provider_thread_id' => 'thr-1',
        ]);
        $this->reply = CrmReply::create([
            'user_id' => $this->user->id, 'crm_contact_id' => $this->contact->id, 'crm_email_send_id' => $send->id, 'crm_campaign_id' => $campaign->id,
            'provider' => 'imap', 'external_id' => '5', 'message_id' => '<r-1@sunrise.example>', 'in_reply_to' => '<camp-x@mybiz.example>',
            'references' => '<camp-x@mybiz.example>', 'from_email' => $this->contact->email, 'from_name' => 'Ada',
            'subject' => 'Re: Quick idea for Sunrise', 'body_text' => 'How much does it cost?', 'body_clean' => 'How much does it cost?',
            'matched_by' => 'message_id', 'received_at' => now()->subHour(),
        ]);
    }

    public function test_the_inbox_lists_conversations_with_their_campaign(): void
    {
        $this->actingAs($this->user)->get(route('crm.inbox'))
            ->assertOk()
            ->assertSee($this->contact->name)
            ->assertSee('How much does it cost?')
            ->assertSee('Dentist launch')
            ->assertSee('1 new');
    }

    public function test_opening_a_conversation_shows_the_thread_and_marks_it_read(): void
    {
        $this->actingAs($this->user)->get(route('crm.inbox.show', $this->contact))
            ->assertOk()
            ->assertSeeInOrder(['Hi Ada, our pitch.', 'How much does it cost?'])
            ->assertSee('Draft reply with AI');

        $this->assertNotNull($this->reply->fresh()->read_at);
    }

    public function test_ai_drafts_a_reply_from_the_thread_and_research_and_charges_credits(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateText')->once()->withArgs(function ($system, $context) {
                return str_contains($context, 'Product: Acme CRM')
                    && str_contains($context, 'ME (')
                    && str_contains($context, 'Their latest message:'."\n".'How much does it cost?')
                    && str_contains($context, 'Offer a call Thursday');
            })->andReturn("Hi Ada,\n\nGreat question — it starts free. Try it here: {{AFFILIATE_LINK}}\n\nBest,");
        });

        $this->actingAs($this->user)
            ->post(route('crm.inbox.draft', $this->reply), ['instructions' => 'Offer a call Thursday'])
            ->assertSessionHas('ai_draft', "Hi Ada,\n\nGreat question — it starts free. Try it here: {affiliate_link}\n\nBest,");

        $this->assertSame(100 - 3, $this->user->fresh()->credits_balance);
    }

    public function test_drafting_needs_credits(): void
    {
        $this->user->update(['credits_balance' => 1]);
        $this->mock(AIProvider::class, fn (MockInterface $mock) => $mock->shouldNotReceive('generateText'));

        $this->actingAs($this->user)->post(route('crm.inbox.draft', $this->reply))->assertSessionHas('error');
    }

    public function test_sending_threads_the_reply_and_tracks_the_affiliate_link(): void
    {
        $this->verifiedSmtp($this->user);
        $captured = null;
        $this->mock(PersonalEmailSender::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('send')->once()->andReturnUsing(function ($connection, $to, $subject, $html, $options) use (&$captured) {
                $captured = compact('to', 'subject', 'html', 'options');

                return null;
            });
        });

        $this->actingAs($this->user)->post(route('crm.inbox.send', $this->reply), [
            'subject' => 'Re: Quick idea for Sunrise',
            'body' => "Hi Ada,\n\nIt starts free: {affiliate_link}\n\nBest,",
        ])->assertRedirect(route('crm.inbox.show', $this->contact))->assertSessionHas('success');

        $this->assertSame($this->contact->email, $captured['to']);
        $this->assertSame('<r-1@sunrise.example>', $captured['options']['in_reply_to']);
        $this->assertSame(['<camp-x@mybiz.example>', '<r-1@sunrise.example>'], $captured['options']['references']);
        $this->assertSame('thr-1', $captured['options']['thread_id']);
        $this->assertStringContainsString('/e/c/', $captured['html']);
        $this->assertStringContainsString('How much does it cost?', $captured['html'], 'The quoted message is included');

        $send = CrmEmailSend::where('kind', 'reply')->sole();
        $this->assertSame('sent', $send->status);
        $this->assertSame('<r-1@sunrise.example>', $send->in_reply_to);
        $this->assertSame($captured['options']['message_id'], $send->message_id);
    }

    public function test_sending_requires_a_verified_connection_and_an_owner(): void
    {
        $this->actingAs($this->user)->post(route('crm.inbox.send', $this->reply), ['subject' => 'Re', 'body' => 'Hi'])->assertSessionHas('error');

        $other = User::factory()->create();
        $other->assignRole('user');
        $this->actingAs($other)->post(route('crm.inbox.send', $this->reply), ['subject' => 'Re', 'body' => 'Hi'])->assertForbidden();
        $this->actingAs($other)->get(route('crm.inbox.show', $this->contact))->assertForbidden();
    }

    public function test_check_now_sends_the_user_to_set_up_reply_detection_when_it_is_off(): void
    {
        $this->actingAs($this->user)->post(route('crm.inbox.check'))->assertRedirect(route('email-connections.index'));
    }
}
