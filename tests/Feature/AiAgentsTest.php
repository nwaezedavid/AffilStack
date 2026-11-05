<?php

namespace Tests\Feature;

use App\Jobs\FindLeads;
use App\Jobs\RunOfferResearch;
use App\Models\AgentActivity;
use App\Models\CrmCampaign;
use App\Models\CrmEmailSend;
use App\Models\CrmReply;
use App\Models\Generation;
use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\AgentUpdate;
use App\Notifications\UpgradeNudgeNotification;
use App\Notifications\WeeklyBriefingNotification;
use App\Notifications\WinBackNotification;
use App\Services\Agents\Customer\CommissionWatchdog;
use App\Services\Agents\Customer\ContentAutopilot;
use App\Services\Agents\Customer\LeadHunter;
use App\Services\Agents\Customer\OfferScout;
use App\Services\Agents\Customer\ReplyCopilot;
use App\Services\Agents\Customer\WeeklyBriefing;
use App\Services\Agents\Revenue\ChurnSaver;
use App\Services\Agents\Revenue\UpgradeNudge;
use App\Services\AI\AIProvider;
use App\Services\Crm\Inbox\ReplyPollingService;
use App\Services\Crm\PersonalEmailSender;
use App\Support\Agents\AgentSettings;
use App\Support\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\Support\CrmTestHelpers;
use Tests\TestCase;

/**
 * The customer AI agents (Reply Copilot, Lead Hunter, Commission Watchdog,
 * Content Autopilot, Offer Scout, Weekly briefing) and the revenue agents
 * (Churn saver, Upgrade nudge).
 */
class AiAgentsTest extends TestCase
{
    use CrmTestHelpers, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->crmUser();
        $this->user->activeSubscription->plan->update(['agents_included' => true, 'agent_autopilot' => true, 'channels' => ['research', 'linkedin', 'x', 'blog', 'google_maps']]);
        $this->user->refresh();
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['93.184.216.34']);
    }

    protected function tearDown(): void
    {
        OutboundUrlGuard::resolveUsing(null);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function fakeAi(array $json): void
    {
        $this->app->instance(AIProvider::class, new class($json) implements AIProvider
        {
            public function __construct(public array $json) {}

            public function generateText(string $systemPrompt, string $userPrompt, array $options = []): string
            {
                return 'Generated text';
            }

            public function generateJson(string $systemPrompt, string $userPrompt, array $options = []): array
            {
                return $this->json;
            }

            public function generateImage(string $prompt, array $options = []): string
            {
                return '';
            }
        });
    }

    protected function replyFrom(string $body): CrmReply
    {
        $offer = $this->offerFor($this->user);
        $list = $this->listWithContacts($this->user, $offer, 1);
        $contact = $list->contacts()->first();
        $campaign = CrmCampaign::factory()->launched()->create(['user_id' => $this->user->id, 'crm_list_id' => $list->id, 'offer_id' => $offer->id]);
        $send = CrmEmailSend::create([
            'user_id' => $this->user->id, 'crm_contact_id' => $contact->id, 'kind' => 'campaign', 'crm_campaign_id' => $campaign->id,
            'subject' => 'Quick idea', 'body' => 'Hi, our pitch.', 'status' => 'sent', 'sent_at' => now()->subDay(),
            'tracking_token' => 'tok-'.uniqid(), 'message_id' => '<c-'.uniqid().'@mybiz.example>',
        ]);

        return CrmReply::create([
            'user_id' => $this->user->id, 'crm_contact_id' => $contact->id, 'crm_email_send_id' => $send->id, 'crm_campaign_id' => $campaign->id,
            'provider' => 'imap', 'external_id' => uniqid(), 'message_id' => '<r-'.uniqid().'@lead.example>', 'from_email' => $contact->email,
            'subject' => 'Re: Quick idea', 'body_text' => $body, 'body_clean' => $body, 'matched_by' => 'message_id', 'received_at' => now(),
        ]);
    }

    public function test_reply_copilot_sorts_a_reply_drafts_an_answer_charges_credits_and_notifies(): void
    {
        Notification::fake();
        $this->fakeAi(['intent' => 'question', 'confidence' => 70, 'summary' => 'Asks about the price.', 'draft' => "Hi Ada,\n\nIt starts at \$29. {{AFFILIATE_LINK}}\n\nBest,"]);
        $reply = $this->replyFrom('How much is it?');

        $this->assertTrue(app(ReplyCopilot::class)->process($reply));

        $reply->refresh();
        $this->assertSame('question', $reply->copilot_intent);
        $this->assertSame('drafted', $reply->copilot_status);
        $this->assertSame(100 - (int) config('crm.credits.reply_draft'), $this->user->fresh()->credits_balance);
        Notification::assertSentTo($this->user, AgentUpdate::class);

        $this->actingAs($this->user)->get(route('crm.inbox.show', $reply->contact))
            ->assertOk()
            ->assertSee('Reply Copilot')
            ->assertSee('Asks about the price.')
            ->assertSee('{affiliate_link}', false);
    }

    public function test_autopilot_answers_safe_replies_after_a_short_pause(): void
    {
        $this->fakeAi(['intent' => 'interested', 'confidence' => 92, 'summary' => 'Wants to try it.', 'draft' => "Hi Ada,\n\nHere you go: {{AFFILIATE_LINK}}\n\nBest,"]);
        AgentSettings::save($this->user, ['reply_copilot' => ['enabled' => true, 'autopilot' => true]] + AgentSettings::DEFAULTS);
        $this->verifiedSmtp($this->user);
        $reply = $this->replyFrom('Sounds great, how do I start?');

        app(ReplyCopilot::class)->process($reply);
        $this->assertSame('auto_queued', $reply->fresh()->copilot_status);
        $this->assertSame(0, app(ReplyCopilot::class)->sendDue(), 'Nothing goes out before the pause ends.');

        $this->mock(PersonalEmailSender::class, fn (MockInterface $mock) => $mock->shouldReceive('send')->once()->andReturn(null));
        $this->travel(15)->minutes();

        $this->assertSame(1, app(ReplyCopilot::class)->sendDue());
        $this->assertSame('sent', $reply->fresh()->copilot_status);
        $this->assertDatabaseHas('crm_email_sends', ['crm_contact_id' => $reply->crm_contact_id, 'kind' => 'reply', 'status' => 'sent']);
        $this->assertTrue(AgentActivity::where('agent', 'reply_copilot')->where('action', 'auto_sent')->exists());
    }

    public function test_the_every_minute_tick_checks_inboxes_and_drafts_new_replies(): void
    {
        Notification::fake();
        $this->fakeAi(['intent' => 'question', 'confidence' => 60, 'summary' => 'Asks a question.', 'draft' => 'Hi Ada, here you go. Best,']);
        $this->verifiedSmtp($this->user, ['imap_credentials' => ['host' => 'imap.hostinger.com', 'port' => 993, 'username' => 'me@mybiz.example', 'password' => 'secret']]);
        $reply = $this->replyFrom('Does it work on mobile?');
        $this->mock(ReplyPollingService::class, fn (MockInterface $mock) => $mock->shouldReceive('pollOne')->andReturn(['replies' => 0]));

        $result = app(ReplyCopilot::class)->tick();

        $this->assertSame(1, $result['drafted']);
        $this->assertSame('drafted', $reply->fresh()->copilot_status);
    }

    public function test_an_unsubscribe_request_is_honoured_and_nothing_is_drafted(): void
    {
        $this->fakeAi(['intent' => 'unsubscribe', 'confidence' => 99, 'summary' => 'Wants no more emails.', 'draft' => '']);
        $reply = $this->replyFrom('Please remove me from your list.');

        $this->assertFalse(app(ReplyCopilot::class)->process($reply));

        $this->assertNotNull($reply->contact->fresh()->unsubscribed_at);
        $this->assertSame('skipped', $reply->fresh()->copilot_status);
        $this->assertSame(100, $this->user->fresh()->credits_balance);
    }

    public function test_agents_do_nothing_on_plans_without_them(): void
    {
        $this->user->activeSubscription->plan->update(['agents_included' => false]);
        $this->fakeAi(['intent' => 'question', 'confidence' => 90, 'summary' => 'x', 'draft' => 'Hi']);
        $reply = $this->replyFrom('Question?');

        $this->assertFalse(app(ReplyCopilot::class)->process($reply->fresh()));
        $this->assertNull($reply->fresh()->copilot_status);

        $this->actingAs($this->user->fresh())->get(route('agents.index'))->assertOk()->assertSee('AI Agents aren\'t included in your current plan', false);
    }

    public function test_the_agents_page_saves_settings_and_keeps_autopilot_off_without_the_plan(): void
    {
        $this->actingAs($this->user)->get(route('agents.index'))->assertOk()->assertSee('Reply Copilot')->assertSee('Offer Scout');

        $this->actingAs($this->user)->put(route('agents.update'), ['agents' => [
            'reply_copilot' => ['enabled' => '1', 'autopilot' => '1'],
            'lead_hunter' => ['enabled' => '1', 'target' => 120],
            'content_autopilot' => ['per_week' => 5],
        ]])->assertSessionHas('success');

        $settings = AgentSettings::for($this->user->fresh());
        $this->assertTrue($settings['reply_copilot']['autopilot']);
        $this->assertTrue($settings['lead_hunter']['enabled']);
        $this->assertSame(120, $settings['lead_hunter']['target']);
        $this->assertFalse($settings['content_autopilot']['enabled']);
        $this->assertFalse($settings['weekly_briefing']['enabled'], 'Unticked switches are off.');

        $this->user->activeSubscription->plan->update(['agent_autopilot' => false]);
        $this->actingAs($this->user->fresh())->put(route('agents.update'), ['agents' => ['reply_copilot' => ['enabled' => '1', 'autopilot' => '1']]]);
        $this->assertFalse(AgentSettings::for($this->user->fresh())['reply_copilot']['autopilot']);
    }

    public function test_commission_watchdog_offers_a_one_tap_swap_to_a_working_link(): void
    {
        Notification::fake();
        $offer = $this->offerFor($this->user);
        $offer->update(['affiliate_link' => 'https://dead.example/ref', 'network_link' => 'https://network.example/track', 'use_network_link' => false]);
        Http::fake([
            'dead.example/*' => Http::response('Not found', 404),
            'network.example/*' => Http::response('ok', 200),
        ]);

        $this->assertSame(1, app(CommissionWatchdog::class)->runFor($this->user));
        $activity = AgentActivity::where('agent', 'commission_watchdog')->firstOrFail();
        $this->assertSame(AgentActivity::STATUS_NEEDS_APPROVAL, $activity->status);
        $this->assertSame(0, app(CommissionWatchdog::class)->runFor($this->user), 'The same problem is raised once a week at most.');

        $this->actingAs($this->user)->get(route('agents.index'))->assertSee('Waiting for your approval');
        $this->actingAs($this->user)->post(route('agents.activities.approve', $activity))->assertSessionHas('success');

        $this->assertTrue($offer->fresh()->use_network_link);
        $this->assertSame(AgentActivity::STATUS_APPROVED, $activity->fresh()->status);

        $stranger = $this->crmUser();
        $this->actingAs($stranger)->post(route('agents.activities.approve', $activity))->assertNotFound();
    }

    public function test_lead_hunter_tops_up_products_below_the_target(): void
    {
        Bus::fake([FindLeads::class]);
        config(['services.google_places.api_key' => 'k']);
        $this->user->activeSubscription->plan->update(['monthly_lead_allowance' => 100]);
        AgentSettings::save($this->user, array_replace_recursive(AgentSettings::DEFAULTS, ['lead_hunter' => ['enabled' => true, 'target' => 30]]));
        $offer = $this->offerFor($this->user);

        $this->assertSame(1, app(LeadHunter::class)->runFor($this->user->fresh()));
        $this->assertDatabaseHas('lead_runs', ['offer_id' => $offer->id, 'trigger' => 'hunter_agent', 'target' => 50]);
        $this->assertSame(0, app(LeadHunter::class)->runFor($this->user->fresh()), 'Not twice in one night.');
    }

    public function test_content_autopilot_plans_the_week_as_calendar_drafts(): void
    {
        Bus::fake();
        AgentSettings::save($this->user, array_replace_recursive(AgentSettings::DEFAULTS, ['content_autopilot' => ['enabled' => true, 'per_week' => 3]]));
        $this->user->update(['credits_balance' => 500]);
        $this->offerFor($this->user);

        $this->assertSame(3, app(ContentAutopilot::class)->runFor($this->user->fresh()));

        $this->assertSame(3, Generation::whereNotNull('scheduled_for')->where('calendar_status', 'draft')->count());
        $this->assertEqualsCanonicalizing(['linkedin_post', 'x_thread', 'blog_article'], Generation::pluck('module')->all());
    }

    public function test_offer_scout_suggests_better_programs_and_approving_researches_one(): void
    {
        Bus::fake([RunOfferResearch::class]);
        $offer = $this->offerFor($this->user);
        $connection = NetworkConnection::create(['user_id' => $this->user->id, 'network' => 'impact', 'status' => 'connected', 'credentials' => []]);
        $program = NetworkProgram::create(['network_connection_id' => $connection->id, 'external_id' => 'p9', 'name' => 'DentalCRM Pro', 'status' => 'joined', 'homepage_url' => 'https://dentalcrm.example', 'tracking_url' => 'https://impact.example/t/9', 'commission' => '40% recurring', 'last_seen_at' => now()]);
        $this->fakeAi(['suggestions' => [['id' => $program->id, 'reason' => 'Same dentist buyers, recurring payouts.', 'gain' => 'about 2x per sale']]]);

        $this->assertSame(1, app(OfferScout::class)->runFor($this->user));
        $activity = AgentActivity::where('agent', 'offer_scout')->firstOrFail();
        $this->assertStringContainsString('DentalCRM Pro', $activity->summary);

        $this->actingAs($this->user)->post(route('agents.activities.approve', $activity))->assertSessionHas('success');
        $this->assertDatabaseHas('offers', ['product_name' => 'DentalCRM Pro', 'network_program_id' => $program->id]);
        Bus::assertDispatched(RunOfferResearch::class);
        $this->assertSame(0, app(OfferScout::class)->runFor($this->user), 'A program is not suggested again for 60 days.');
    }

    public function test_the_weekly_briefing_goes_out_once_a_week(): void
    {
        Notification::fake();
        $this->offerFor($this->user);

        $this->assertTrue(app(WeeklyBriefing::class)->runFor($this->user));
        $this->assertFalse(app(WeeklyBriefing::class)->runFor($this->user));

        Notification::assertSentTo($this->user, WeeklyBriefingNotification::class, function (WeeklyBriefingNotification $n) {
            return count($n->briefing['next_steps']) === 3;
        });
    }

    public function test_churn_saver_writes_to_quiet_paying_customers_when_switched_on(): void
    {
        Notification::fake();
        $this->user->forceFill(['last_active_at' => now()->subDays(20)])->save();

        $this->assertSame(0, app(ChurnSaver::class)->run(), 'Off until the admin switches it on.');

        SiteSetting::set('agents_churn_saver_enabled', '1');
        SiteSetting::set('agents_churn_saver_offer', 'Reply STAY for 200 free credits.');

        $this->assertSame(1, app(ChurnSaver::class)->run());
        $this->assertSame(0, app(ChurnSaver::class)->run(), 'Once a month at most.');
        Notification::assertSentTo($this->user, WinBackNotification::class, fn (WinBackNotification $n) => $n->offer === 'Reply STAY for 200 free credits.');
    }

    public function test_upgrade_nudge_shows_on_the_dashboard_and_emails_once_a_month(): void
    {
        Notification::fake();
        Plan::factory()->create(['price_monthly_cents' => 99900, 'is_active' => true]);
        $this->user->activeSubscription->plan->update(['credits_per_month' => 100, 'price_monthly_cents' => 1900]);
        $this->user->update(['credits_balance' => 10]);
        SiteSetting::set('agents_upgrade_nudge_enabled', '1');

        $this->assertSame('credits', app(UpgradeNudge::class)->nearLimits($this->user->fresh())[0]['key']);
        $this->actingAs($this->user->fresh())->get(route('dashboard'))->assertOk()->assertSee('of your monthly credits this month');

        $this->assertSame(1, app(UpgradeNudge::class)->run());
        $this->assertSame(0, app(UpgradeNudge::class)->run());
        Notification::assertSentTo($this->user, UpgradeNudgeNotification::class);
    }
}
