<?php

namespace Tests\Feature;

use App\Models\CrmCampaign;
use App\Models\CrmCampaignRecipient;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\CrmList;
use App\Models\User;
use App\Notifications\CrmCampaignPaused;
use App\Services\AI\AIProvider;
use App\Services\Crm\CampaignManager;
use App\Services\Crm\PersonalEmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\CrmTestHelpers;
use Tests\TestCase;

/**
 * CRM campaigns end to end: AI drafting (charged), editing, Approve and
 * Send with caps/unsubscribes/dedupe, send-to-new, autopilot, follow-ups
 * that stop on reply, and pausing when the sender breaks.
 */
class CrmCampaignTest extends TestCase
{
    use CrmTestHelpers, RefreshDatabase;

    /** @var array<int, array{to: string, subject: string, html: string, options: array<string, mixed>}> */
    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['crm.sending.pause_ms' => 0]);
    }

    protected function captureSends(?\Closure $failWhen = null): void
    {
        $this->mock(PersonalEmailSender::class, function (MockInterface $mock) use ($failWhen) {
            $mock->shouldReceive('send')->andReturnUsing(function ($connection, $to, $subject, $html, $options = []) use ($failWhen) {
                if ($failWhen && $failWhen($to)) {
                    throw new RuntimeException('SMTP send failed: Expected response code "235" but got code "535", with message "535 5.7.8 Authentication failed".');
                }

                $this->sent[] = compact('to', 'subject', 'html', 'options');

                return null;
            });
        });
    }

    protected function fakeCampaignAi(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->withArgs(function ($system, $user) {
                return str_contains($user, 'Product: Acme CRM') && str_contains($user, 'Pain points it solves: Missed calls');
            })->andReturn([
                'subject' => 'Quick idea for {company}',
                'body' => "Hi {first_name},\n\nI saw {company} in {city}.\n\nHave a look: {affiliate_link}\n\nBest,",
                'follow_ups' => [
                    ['delay_days' => 3, 'subject' => '', 'body' => "Hi {first_name}, one more idea.\n\nBest,"],
                    ['delay_days' => 4, 'subject' => 'Closing the loop', 'body' => "Hi {first_name}, last note.\n\nBest,"],
                ],
            ]);
        });
    }

    /**
     * @return array{0: User, 1: CrmList}
     */
    protected function userWithList(int $contacts = 2): array
    {
        $user = $this->crmUser();
        $list = $this->listWithContacts($user, $this->offerFor($user), $contacts);

        return [$user, $list];
    }

    protected function draftCampaign(User $user, CrmList $list, array $attributes = []): CrmCampaign
    {
        return CrmCampaign::factory()->create($attributes + [
            'user_id' => $user->id, 'crm_list_id' => $list->id, 'offer_id' => $list->offer_id,
        ]);
    }

    public function test_the_create_page_preselects_the_list_and_shows_the_cost(): void
    {
        [$user, $list] = $this->userWithList();

        $this->actingAs($user)->get(route('crm.campaigns.create', ['list' => $list->id]))
            ->assertOk()
            ->assertViewHas('selected', fn ($selected) => $selected->is($list))
            ->assertSee('8')
            ->assertSee('Write it with AI');
    }

    public function test_ai_writes_a_draft_from_the_product_research_and_charges_credits(): void
    {
        [$user, $list] = $this->userWithList();
        $this->fakeCampaignAi();

        $response = $this->actingAs($user)->post(route('crm.campaigns.store'), ['list_id' => $list->id, 'mode' => 'ai', 'follow_ups' => 2]);

        $campaign = CrmCampaign::firstOrFail();
        $response->assertRedirect(route('crm.campaigns.show', $campaign));
        $this->assertSame('draft', $campaign->status);
        $this->assertSame('Quick idea for {company}', $campaign->subject);
        $this->assertCount(2, $campaign->followUps());
        $this->assertSame(100 - 8, $user->fresh()->credits_balance);
        $this->assertSame(8, $campaign->credits_spent);
    }

    public function test_ai_drafting_is_refused_without_enough_credits(): void
    {
        [$user, $list] = $this->userWithList();
        $user->update(['credits_balance' => 3]);
        $this->mock(AIProvider::class, fn (MockInterface $mock) => $mock->shouldNotReceive('generateJson'));

        $this->actingAs($user)->post(route('crm.campaigns.store'), ['list_id' => $list->id, 'mode' => 'ai'])->assertSessionHas('error');

        $this->assertSame(0, CrmCampaign::count());
        $this->assertSame(3, $user->fresh()->credits_balance);
    }

    public function test_write_it_myself_creates_a_free_blank_draft(): void
    {
        [$user, $list] = $this->userWithList();

        $this->actingAs($user)->post(route('crm.campaigns.store'), ['list_id' => $list->id, 'mode' => 'manual']);

        $this->assertSame('draft', CrmCampaign::firstOrFail()->status);
        $this->assertSame(100, $user->fresh()->credits_balance);
    }

    public function test_the_editor_previews_merge_fields_for_a_real_contact(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $campaign = $this->draftCampaign($user, $list);

        $this->actingAs($user)->get(route('crm.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Approve and Send to 1')
            ->assertViewHas('samples', fn ($samples) => $samples[0]['values']['city'] === 'Ikeja' && str_contains($samples[0]['values']['affiliate_link'], '/go/'));
    }

    public function test_approving_requires_a_verified_email_connection(): void
    {
        [$user, $list] = $this->userWithList();
        $campaign = $this->draftCampaign($user, $list);

        $this->actingAs($user)->put(route('crm.campaigns.update', $campaign), [
            'name' => 'C', 'subject' => 'Hi {first_name}', 'body' => 'Body', 'intent' => 'approve',
        ])->assertSessionHas('error');

        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_approve_and_send_personalises_each_email_and_records_history(): void
    {
        [$user, $list] = $this->userWithList(2);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list);

        $this->actingAs($user)->put(route('crm.campaigns.update', $campaign), [
            'name' => 'Launch', 'subject' => 'Quick idea for {company}',
            'body' => "Hi {first_name},\n\nI saw {company} in {city} ({website}).\n\n{affiliate_link}\n\nBest,",
            'follow_ups' => [['delay_days' => 3, 'subject' => '', 'body' => 'Hi {first_name}, bumping this.']],
            'intent' => 'approve',
        ])->assertRedirect(route('crm.campaigns.show', $campaign))->assertSessionHas('success');

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertCount(2, $this->sent);

        $first = $this->sent[0];
        $contact = CrmContact::where('email', $first['to'])->firstOrFail();
        $this->assertSame('Quick idea for '.$contact->company, $first['subject']);
        $this->assertStringContainsString('Hi Ada', $first['html']);
        $this->assertStringContainsString('in Ikeja (sunrise.example)', $first['html']);
        $this->assertStringContainsString('/e/c/', $first['html'], 'Affiliate link is click-tracked');
        $this->assertStringContainsString('Unsubscribe', $first['html']);
        $this->assertStringContainsString('1 Marina, Lagos', $first['html']);
        $this->assertStringContainsString('/crm/unsubscribe/', $first['options']['list_unsubscribe']);

        $send = CrmEmailSend::where('crm_contact_id', $contact->id)->firstOrFail();
        $this->assertSame('campaign', $send->kind);
        $this->assertSame($first['options']['message_id'], $send->message_id);
        $this->assertMatchesRegularExpression('/^<[^@]+@mybiz\.example>$/', $send->message_id);

        $recipient = CrmCampaignRecipient::where('crm_contact_id', $contact->id)->firstOrFail();
        $this->assertSame('sent', $recipient->status);
        $this->assertSame(1, $recipient->steps_sent);
        $this->assertTrue($recipient->next_step_due_at->isFuture());
        $this->assertSame('contacted', $contact->fresh()->status);

        $this->actingAs($user)->get(route('crm.campaigns.show', $campaign))->assertOk()->assertSee('Recipients')->assertSee($contact->name);
    }

    public function test_unsubscribed_and_email_less_contacts_are_skipped(): void
    {
        [$user, $list] = $this->userWithList(1);
        $list->addContact($this->contactFor($user, ['email' => null, 'name' => 'No Mail']));
        $list->addContact($this->contactFor($user, ['unsubscribed_at' => now(), 'name' => 'Gone']));
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list);

        app(CampaignManager::class)->approve($campaign);

        $this->assertCount(1, $this->sent);
        $statuses = $campaign->recipients()->with('contact')->get()->mapWithKeys(fn ($r) => [$r->contact->name => $r->status]);
        $this->assertSame('skipped', $statuses['No Mail']);
        $this->assertSame('unsubscribed', $statuses['Gone']);
    }

    public function test_the_daily_cap_holds_sends_back_until_tomorrow(): void
    {
        [$user, $list] = $this->userWithList(3);
        $this->verifiedSmtp($user, ['daily_limit' => 2]);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list);

        app(CampaignManager::class)->approve($campaign);

        $this->assertCount(2, $this->sent);
        $this->assertSame(1, $campaign->recipients()->where('status', 'queued')->count());
        $this->assertSame('sending', $campaign->fresh()->status);

        $this->travel(1)->days();
        $this->artisan('crm:send-campaigns')->assertSuccessful();

        $this->assertCount(3, $this->sent);
        $this->assertSame('sent', $campaign->fresh()->status);
    }

    public function test_a_contact_is_never_sent_the_same_campaign_twice(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list);
        $manager = app(CampaignManager::class);

        $manager->approve($campaign);
        $this->assertSame(0, $manager->sendToNewContacts($campaign->fresh()));
        $manager->process($campaign->fresh());

        $this->assertCount(1, $this->sent);
    }

    public function test_send_to_new_contacts_reaches_only_people_added_since_launch(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list);
        app(CampaignManager::class)->approve($campaign);

        $newcomer = $this->contactFor($user, ['email' => 'late@joiner.example']);
        $list->addContact($newcomer);

        $this->actingAs($user)->get(route('crm.campaigns.show', $campaign))->assertSee('Send to 1 new contact');
        $this->actingAs($user)->post(route('crm.campaigns.send-new', $campaign))->assertSessionHas('success');

        $this->assertCount(2, $this->sent);
        $this->assertSame('late@joiner.example', $this->sent[1]['to']);
        $this->assertSame('new_contacts', CrmCampaignRecipient::where('crm_contact_id', $newcomer->id)->value('source'));
    }

    public function test_autopilot_emails_every_contact_newly_added_to_the_list(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list, ['autopilot' => true]);
        app(CampaignManager::class)->approve($campaign);
        $this->assertCount(1, $this->sent);

        $newcomer = $this->contactFor($user, ['email' => 'auto@pilot.example']);
        $this->assertTrue($list->addContact($newcomer));
        $this->assertFalse($list->addContact($newcomer));

        $this->assertCount(2, $this->sent);
        $this->assertSame('auto@pilot.example', $this->sent[1]['to']);
        $this->assertSame('autopilot', CrmCampaignRecipient::where('crm_contact_id', $newcomer->id)->value('source'));
    }

    public function test_autopilot_does_nothing_for_drafts_or_when_turned_off(): void
    {
        [$user, $list] = $this->userWithList(0);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $this->draftCampaign($user, $list, ['autopilot' => true]);
        $launched = $this->draftCampaign($user, $list, ['autopilot' => true]);
        app(CampaignManager::class)->approve($launched);

        $this->actingAs($user)->patch(route('crm.campaigns.autopilot', $launched), ['autopilot' => 0])->assertSessionHas('success');
        $list->addContact($this->contactFor($user));

        $this->assertCount(0, $this->sent);
        $this->assertSame(0, CrmCampaignRecipient::count());
    }

    public function test_follow_ups_go_out_when_due_and_thread_under_the_first_email(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list, ['follow_up_steps' => [['delay_days' => 3, 'subject' => '', 'body' => 'Hi {first_name}, following up.']]]);
        app(CampaignManager::class)->approve($campaign);

        $this->travel(2)->days();
        $this->artisan('crm:send-campaigns');
        $this->assertCount(1, $this->sent);

        $this->travel(2)->days();
        $this->artisan('crm:send-campaigns');
        $this->assertCount(2, $this->sent);

        $followUp = $this->sent[1];
        $this->assertStringStartsWith('Re: ', $followUp['subject']);
        $this->assertSame($this->sent[0]['options']['message_id'], $followUp['options']['in_reply_to']);
        $this->assertSame([$this->sent[0]['options']['message_id']], $followUp['options']['references']);

        $recipient = CrmCampaignRecipient::firstOrFail();
        $this->assertSame(2, $recipient->steps_sent);
        $this->assertNull($recipient->next_step_due_at);
    }

    public function test_follow_ups_stop_for_a_contact_who_replied(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list, ['follow_up_steps' => [['delay_days' => 2, 'subject' => '', 'body' => 'Bump']]]);
        app(CampaignManager::class)->approve($campaign);

        CrmCampaignRecipient::firstOrFail()->update(['status' => 'replied', 'replied_at' => now()]);

        $this->travel(3)->days();
        $this->artisan('crm:send-campaigns');

        $this->assertCount(1, $this->sent);
    }

    public function test_a_broken_sender_pauses_the_campaign_and_tells_the_user(): void
    {
        Notification::fake();
        [$user, $list] = $this->userWithList(2);
        $connection = $this->verifiedSmtp($user);
        $this->captureSends(fn () => true);
        $campaign = $this->draftCampaign($user, $list, ['autopilot' => true]);

        app(CampaignManager::class)->approve($campaign);

        $campaign->refresh();
        $this->assertSame('paused', $campaign->status);
        $this->assertSame('connection', $campaign->paused_reason);
        $this->assertSame('failed', $connection->fresh()->verification_status);
        $this->assertStringContainsString('rejected the username or password', $connection->fresh()->verification_message);
        $this->assertSame(1, $campaign->recipients()->where('status', 'queued')->count(), 'The second contact waits for the fix');
        Notification::assertSentTo($user, CrmCampaignPaused::class);

        $list->addContact($this->contactFor($user));
        $this->assertSame(2, $campaign->recipients()->where('status', 'queued')->count(), 'Autopilot still queues while paused');

        $this->actingAs($user)->post(route('crm.campaigns.resume', $campaign))->assertSessionHas('error');
    }

    public function test_the_ai_opening_line_is_generated_per_contact_and_charged(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $this->mock(AIProvider::class, fn (MockInterface $mock) => $mock->shouldReceive('generateText')->once()->andReturn('Loved the new Ikeja branch photos on your site.'));
        $campaign = $this->draftCampaign($user, $list, ['personalize_opening' => true, 'body' => "Hi {first_name},\n\nMain pitch.\n\nBest,"]);

        app(CampaignManager::class)->approve($campaign);

        $this->assertStringContainsString('Hi Ada', $this->sent[0]['html']);
        $this->assertMatchesRegularExpression('/Hi Ada\d*,<br \/>\s*<br \/>\s*Loved the new Ikeja branch photos/', $this->sent[0]['html']);
        $this->assertSame(100 - 1, $user->fresh()->credits_balance);
        $this->assertSame('Loved the new Ikeja branch photos on your site.', CrmCampaignRecipient::first()->opening_line);
    }

    public function test_pause_and_resume(): void
    {
        [$user, $list] = $this->userWithList(1);
        $this->verifiedSmtp($user);
        $this->captureSends();
        $campaign = $this->draftCampaign($user, $list);
        app(CampaignManager::class)->approve($campaign);

        $this->actingAs($user)->post(route('crm.campaigns.pause', $campaign))->assertSessionHas('success');
        $this->assertTrue($campaign->fresh()->isPaused());

        $this->actingAs($user)->post(route('crm.campaigns.resume', $campaign))->assertSessionHas('success');
        $this->assertFalse($campaign->fresh()->isPaused());
    }

    public function test_campaigns_are_private_to_their_owner(): void
    {
        [$user, $list] = $this->userWithList();
        $campaign = $this->draftCampaign($user, $list);
        $other = User::factory()->create();
        $other->assignRole('user');

        $this->actingAs($other)->get(route('crm.campaigns.show', $campaign))->assertForbidden();
        $this->actingAs($other)->post(route('crm.campaigns.send-new', $campaign))->assertForbidden();
    }

    public function test_the_history_index_lists_campaigns(): void
    {
        [$user, $list] = $this->userWithList();
        $this->draftCampaign($user, $list, ['name' => 'My Draft Campaign']);

        $this->actingAs($user)->get(route('crm.campaigns.index'))->assertOk()->assertSee('My Draft Campaign');
    }
}
