<?php

namespace Tests\Feature\Api\V1;

use App\Models\AgentActivity;
use App\Models\ApiToken;
use App\Models\CrmContact;
use App\Models\CrmReply;
use App\Models\Generation;
use App\Models\MobileDevice;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Crm\Inbox\InboxReplier;
use App\Services\Modules\QuickVideoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The phone app's core screens (phase 4): products and content, videos,
 * AI agent approvals, inbox replies and CRM contacts.
 */
class MobileCoreScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Queue::fake();
    }

    /**
     * @param  array<int, string>  $channels
     */
    protected function customer(array $channels = ['research', 'blog', 'linkedin'], array $attributes = []): User
    {
        $user = User::factory()->create(['credits_balance' => 1000] + $attributes);
        $user->assignRole('user');
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => Plan::factory()->create(['channels' => $channels])->id]);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    protected function phone(User $user): array
    {
        $device = MobileDevice::factory()->for($user)->create();
        $plain = 'afmt_'.Str::random(48);

        ApiToken::create([
            'user_id' => $user->id,
            'mobile_device_id' => $device->id,
            'type' => 'mobile',
            'scope' => 'full',
            'name' => 'AffilStack app',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return ['Authorization' => 'Bearer '.$plain];
    }

    protected function offer(User $user, array $attributes = []): Offer
    {
        return Offer::create($attributes + [
            'user_id' => $user->id,
            'product_name' => 'Widget Pro',
            'product_url' => 'https://example.com/widget',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
            'ideal_customer_summary' => 'Busy founders',
            'recommended_angle' => 'Save an hour a day',
        ]);
    }

    protected function seat(User $owner): User
    {
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $this->offer($owner)->id]);
        $seat->assignRole('user');

        return $seat;
    }

    // --- Products and content ------------------------------------------------

    public function test_products_list_only_the_customers_own_and_can_be_searched(): void
    {
        $user = $this->customer();
        $this->offer($user);
        $this->offer($user, ['product_name' => 'Gadget Max']);
        $this->offer($this->customer(), ['product_name' => 'Someone else']);

        $this->getJson('/api/v1/mobile/offers', $this->phone($user))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/mobile/offers?q=gadget', $this->phone($user))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product_name', 'Gadget Max');
    }

    public function test_a_product_shows_its_research_content_and_what_can_be_created(): void
    {
        $user = $this->customer();
        $offer = $this->offer($user);
        Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'completed', 'output' => '<p>Hello <strong>world</strong></p>', 'output_meta' => ['title' => 'Why Widget Pro']]);

        $this->getJson("/api/v1/mobile/offers/{$offer->id}", $this->phone($user))
            ->assertOk()
            ->assertJsonPath('offer.research.ideal_customer', 'Busy founders')
            ->assertJsonPath('content.0.title', 'Why Widget Pro')
            ->assertJsonPath('content.0.plain_text', 'Hello world')
            ->assertJsonPath('content_types.0.type', 'blog_article')
            ->assertJsonPath('content_types.0.credits', (int) config('credits.costs.blog_article'));
    }

    public function test_someone_elses_product_is_not_found(): void
    {
        $offer = $this->offer($this->customer());

        $this->getJson("/api/v1/mobile/offers/{$offer->id}", $this->phone($this->customer()))->assertNotFound();
    }

    public function test_creating_content_queues_the_same_module_as_the_dashboard(): void
    {
        $user = $this->customer();
        $offer = $this->offer($user);

        $this->postJson("/api/v1/mobile/offers/{$offer->id}/content", ['type' => 'blog_article', 'keyword' => 'best widget'], $this->phone($user))
            ->assertCreated()
            ->assertJsonPath('data.module', 'blog_article')
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('generations', ['offer_id' => $offer->id, 'module' => 'blog_article', 'status' => 'queued']);
    }

    public function test_content_outside_the_plan_or_without_credits_is_refused(): void
    {
        $user = $this->customer(['research', 'blog']);
        $offer = $this->offer($user);

        $this->postJson("/api/v1/mobile/offers/{$offer->id}/content", ['type' => 'x_thread'], $this->phone($user))->assertForbidden();

        $broke = $this->customer(attributes: ['credits_balance' => 0]);
        $brokeOffer = $this->offer($broke);

        $this->postJson("/api/v1/mobile/offers/{$brokeOffer->id}/content", ['type' => 'blog_article'], $this->phone($broke))->assertStatus(402);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_a_generation_can_be_polled_by_its_owner_only(): void
    {
        $user = $this->customer();
        $generation = Generation::create(['user_id' => $user->id, 'offer_id' => $this->offer($user)->id, 'module' => 'linkedin_post', 'status' => 'processing']);

        $this->getJson("/api/v1/mobile/generations/{$generation->id}", $this->phone($user))->assertOk()->assertJsonPath('data.status', 'processing')->assertJsonPath('data.text', null);
        $this->getJson("/api/v1/mobile/generations/{$generation->id}", $this->phone($this->customer()))->assertNotFound();
    }

    // --- Videos ----------------------------------------------------------------

    public function test_video_styles_list_the_three_styles_with_prices(): void
    {
        $user = $this->customer(['ugc']);

        $this->getJson('/api/v1/mobile/videos/styles', $this->phone($user))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['style', 'label', 'credits', 'duration_seconds', 'uses_product_photo', 'available']]]);
    }

    public function test_creating_a_video_passes_the_style_and_photo_to_quick_video(): void
    {
        $user = $this->customer(['ugc']);
        $offer = $this->offer($user);
        $photo = UploadedFile::fake()->image('product.jpg', 800, 800);

        $this->mock(QuickVideoService::class, function (MockInterface $mock) use ($user, $offer): void {
            $mock->shouldReceive('queue')
                ->once()
                ->withArgs(fn ($who, $what, $style, $options) => $who->is($user) && $what->is($offer) && $style === 'product_showcase' && $options['image'] instanceof UploadedFile)
                ->andReturnUsing(fn () => Generation::create(['user_id' => $user->id, 'offer_id' => $offer->id, 'module' => 'ugc_video', 'status' => 'queued', 'input' => ['quick_style' => 'product_showcase']]));
        });

        $this->post("/api/v1/mobile/offers/{$offer->id}/videos", ['style' => 'product_showcase', 'product_image' => $photo], $this->phone($user) + ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.module', 'ugc_video')
            ->assertJsonPath('data.video_style', 'product_showcase');
    }

    public function test_videos_need_the_plan_and_are_not_for_team_seats(): void
    {
        $user = $this->customer(['research']);
        $offer = $this->offer($user);

        $this->postJson("/api/v1/mobile/offers/{$offer->id}/videos", ['style' => 'talking_review'], $this->phone($user))->assertForbidden();

        $owner = $this->customer(['ugc']);
        $seat = $this->seat($owner);

        $this->postJson("/api/v1/mobile/offers/{$seat->seat_offer_id}/videos", ['style' => 'talking_review'], $this->phone($seat))
            ->assertForbidden()
            ->assertJsonPath('message', 'Ask your account owner to create videos — team seats can write and edit scripts.');
    }

    public function test_finished_videos_list_with_their_address(): void
    {
        $user = $this->customer(['ugc']);
        Generation::create(['user_id' => $user->id, 'offer_id' => $this->offer($user)->id, 'module' => 'ugc_video', 'status' => 'completed', 'output' => 'https://cdn.example.com/v.mp4', 'input' => ['quick_style' => 'talking_review']]);

        $this->getJson('/api/v1/mobile/videos', $this->phone($user))
            ->assertOk()
            ->assertJsonPath('data.0.video_url', 'https://cdn.example.com/v.mp4')
            ->assertJsonPath('data.0.offer_name', 'Widget Pro');
    }

    // --- AI agents -----------------------------------------------------------------

    public function test_agents_list_approvals_and_activity(): void
    {
        $user = $this->customer();
        AgentActivity::create(['user_id' => $user->id, 'agent' => 'offer_scout', 'action' => 'found', 'summary' => 'A new program', 'status' => AgentActivity::STATUS_NEEDS_APPROVAL, 'meta' => ['approval' => 'research_program']]);
        AgentActivity::create(['user_id' => $user->id, 'agent' => 'weekly_briefing', 'action' => 'briefing', 'summary' => 'Your week', 'status' => AgentActivity::STATUS_DONE]);

        $this->getJson('/api/v1/mobile/agents', $this->phone($user))
            ->assertOk()
            ->assertJsonPath('approvals.0.agent_name', 'Offer Scout')
            ->assertJsonPath('approvals.0.approve_label', 'Research it')
            ->assertJsonPath('activity.0.agent_name', 'Weekly briefing');
    }

    public function test_approving_a_link_swap_uses_the_dashboards_approval(): void
    {
        $user = $this->customer();
        $offer = $this->offer($user, ['affiliate_link' => 'https://aff.example/a', 'network_link' => 'https://net.example/b', 'use_network_link' => false]);
        $activity = AgentActivity::create(['user_id' => $user->id, 'agent' => 'commission_watchdog', 'action' => 'swap', 'summary' => 'Switch link', 'status' => AgentActivity::STATUS_NEEDS_APPROVAL, 'meta' => ['approval' => 'swap_link', 'offer_id' => $offer->id, 'use_network_link' => true]]);

        $this->postJson("/api/v1/mobile/agents/activities/{$activity->id}/approve", [], $this->phone($user))
            ->assertOk()
            ->assertJsonPath('data.status', AgentActivity::STATUS_APPROVED);

        $this->assertTrue($offer->fresh()->use_network_link);
        $this->postJson("/api/v1/mobile/agents/activities/{$activity->id}/approve", [], $this->phone($user))->assertUnprocessable();
    }

    public function test_dismissing_and_other_peoples_suggestions(): void
    {
        $user = $this->customer();
        $activity = AgentActivity::create(['user_id' => $user->id, 'agent' => 'offer_scout', 'action' => 'found', 'summary' => 'Idea', 'status' => AgentActivity::STATUS_NEEDS_APPROVAL]);

        $this->postJson("/api/v1/mobile/agents/activities/{$activity->id}/dismiss", [], $this->phone($this->customer()))->assertNotFound();
        $this->postJson("/api/v1/mobile/agents/activities/{$activity->id}/dismiss", [], $this->phone($user))->assertOk();

        $this->assertSame(AgentActivity::STATUS_DISMISSED, $activity->fresh()->status);
    }

    public function test_team_seats_cannot_use_agents_or_the_inbox_as_on_the_website(): void
    {
        $seat = $this->seat($this->customer());

        $this->getJson('/api/v1/mobile/agents', $this->phone($seat))->assertForbidden();
        $this->getJson('/api/v1/mobile/inbox', $this->phone($seat))->assertForbidden();
    }

    // --- Inbox -------------------------------------------------------------------

    protected function reply(User $user, CrmContact $contact, array $attributes = []): CrmReply
    {
        return CrmReply::create($attributes + [
            'user_id' => $user->id,
            'crm_contact_id' => $contact->id,
            'provider' => 'imap',
            'from_email' => $contact->email,
            'message_id' => Str::random(20).'@example.com',
            'matched_by' => 'sender',
            'subject' => 'Re: Widget Pro',
            'body_text' => "Sounds interesting — how much is it?\n\nThanks",
            'received_at' => now(),
        ]);
    }

    public function test_the_inbox_lists_conversations_with_unread_counts(): void
    {
        $user = $this->customer();
        $ada = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'contacted']);
        $this->reply($user, $ada, ['copilot_status' => 'drafted', 'copilot_draft' => 'Hi Ada!']);

        $this->getJson('/api/v1/mobile/inbox', $this->phone($user))
            ->assertOk()
            ->assertJsonPath('data.0.contact_name', 'Ada')
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.has_suggestion', true)
            ->assertJsonPath('email_connected', false);
    }

    public function test_opening_a_conversation_marks_it_read_and_shows_the_suggestion(): void
    {
        $user = $this->customer();
        $ada = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'contacted']);
        $reply = $this->reply($user, $ada, ['copilot_status' => 'drafted', 'copilot_draft' => 'Hi Ada, it is $49.']);

        $this->getJson("/api/v1/mobile/inbox/{$ada->id}", $this->phone($user))
            ->assertOk()
            ->assertJsonPath('reply_id', $reply->id)
            ->assertJsonPath('reply_subject', 'Re: Widget Pro')
            ->assertJsonPath('messages.0.direction', 'in')
            ->assertJsonPath('suggestion.body', 'Hi Ada, it is $49.');

        $this->assertNotNull($reply->fresh()->read_at);
        $this->getJson("/api/v1/mobile/inbox/{$ada->id}", $this->phone($this->customer()))->assertNotFound();
    }

    public function test_an_ai_draft_comes_from_the_inbox_replier(): void
    {
        $user = $this->customer();
        $ada = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'contacted']);
        $reply = $this->reply($user, $ada);

        $this->partialMock(InboxReplier::class, fn (MockInterface $mock) => $mock->shouldReceive('draft')->once()->andReturn('Thanks Ada — it is $49.'));

        $this->postJson("/api/v1/mobile/inbox/replies/{$reply->id}/draft", ['instructions' => 'mention the price'], $this->phone($user))
            ->assertOk()
            ->assertJsonPath('body', 'Thanks Ada — it is $49.');
    }

    public function test_sending_needs_a_verified_email_connection(): void
    {
        $user = $this->customer();
        $ada = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'contacted']);
        $reply = $this->reply($user, $ada);

        $this->postJson("/api/v1/mobile/inbox/replies/{$reply->id}/send", ['subject' => 'Re: Widget Pro', 'body' => 'Hello'], $this->phone($user))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Connect and verify your email on the Email Sending page before replying.');
    }

    public function test_dismissing_a_suggestion(): void
    {
        $user = $this->customer();
        $ada = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'contacted']);
        $reply = $this->reply($user, $ada, ['copilot_status' => 'drafted', 'copilot_draft' => 'Hi']);

        $this->postJson("/api/v1/mobile/inbox/replies/{$reply->id}/suggestion/dismiss", [], $this->phone($user))->assertOk();

        $this->assertSame('dismissed', $reply->fresh()->copilot_status);
    }

    // --- Contacts ------------------------------------------------------------------

    public function test_contacts_can_be_searched_and_filtered(): void
    {
        $user = $this->customer();
        CrmContact::create(['user_id' => $user->id, 'name' => 'Ada Lovelace', 'company' => 'Analytical', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'qualified', 'tags' => ['vip']]);
        CrmContact::create(['user_id' => $user->id, 'name' => 'Grace Hopper', 'email' => 'grace@example.com', 'source' => 'manual', 'status' => 'new']);
        CrmContact::create(['user_id' => $this->customer()->id, 'name' => 'Ada Other', 'source' => 'manual', 'status' => 'new']);

        $headers = $this->phone($user);

        $this->getJson('/api/v1/mobile/contacts', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/mobile/contacts?q=analytical', $headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ada Lovelace');
        $this->getJson('/api/v1/mobile/contacts?status=new', $headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Grace Hopper');
        $this->getJson('/api/v1/mobile/contacts?tag=vip', $headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.tags.0', 'vip');
    }

    public function test_a_contacts_status_tags_and_notes_can_be_updated(): void
    {
        $user = $this->customer();
        $contact = CrmContact::create(['user_id' => $user->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'source' => 'manual', 'status' => 'new']);

        $this->patchJson("/api/v1/mobile/contacts/{$contact->id}", ['status' => 'qualified', 'tags' => [' vip ', 'vip', 'warm'], 'notes' => 'Call Friday'], $this->phone($user))
            ->assertOk()
            ->assertJsonPath('data.status', 'qualified')
            ->assertJsonPath('data.tags', ['vip', 'warm'])
            ->assertJsonPath('data.notes', 'Call Friday');

        $this->patchJson("/api/v1/mobile/contacts/{$contact->id}", ['status' => 'banana'], $this->phone($user))->assertUnprocessable();
        $this->patchJson("/api/v1/mobile/contacts/{$contact->id}", ['notes' => 'x'], $this->phone($this->customer()))->assertNotFound();
    }

    public function test_developer_tokens_cannot_use_the_core_screens(): void
    {
        $user = $this->customer();
        $plain = ApiToken::generate($user, 'Script')['plainText'];

        $this->getJson('/api/v1/mobile/offers', ['Authorization' => "Bearer {$plain}"])->assertForbidden();
    }
}
