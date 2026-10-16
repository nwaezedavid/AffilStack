<?php

namespace Tests\Feature;

use App\Jobs\RunBlogArticle;
use App\Jobs\SendArticleToWordPress;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WordpressDelivery;
use App\Models\WordpressSite;
use App\Services\AI\AIProvider;
use App\Services\Modules\BlogArticleService;
use App\Services\WordPress\ArticlePayload;
use App\Services\WordPress\ConnectorSignature;
use App\Services\WordPress\WordPressDispatcher;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 4: sending finished blog articles to connected WordPress sites
 * as drafts — on request or automatically — and what the plugin receives.
 */
class WordPressDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('public');
    }

    protected function customer(int $sitesLimit = 3): User
    {
        $user = User::factory()->create(['credits_balance' => 500]);
        $user->assignRole('user');
        $plan = Plan::factory()->create(['wordpress_sites_limit' => $sitesLimit]);
        Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user->fresh();
    }

    protected function article(User $user, array $meta = [], ?string $markdown = null): Generation
    {
        $offer = Offer::create([
            'user_id' => $user->id,
            'product_name' => 'Acme Mailer',
            'product_url' => 'https://acme.example',
            'affiliate_url' => 'https://partners.acme.example/?ref=me',
            'affiliate_network' => 'ShareASale',
            'status' => 'ready',
        ]);

        return Generation::create([
            'user_id' => $user->id,
            'offer_id' => $offer->id,
            'module' => 'blog_article',
            'status' => 'completed',
            'input' => ['target_keyword' => 'best email tool'],
            'output' => $markdown ?? "# Best Email Tool for Freelancers\n\nTry [Acme Mailer](".Offer::LINK_PLACEHOLDER.") today.\n\n## Why it wins\n\nIt is <script>alert(1)</script> simple.",
            'output_meta' => array_merge([
                'title' => 'Best Email Tool for Freelancers',
                'slug' => 'best-email-tool-freelancers',
                'meta_description' => 'Which email tool should a freelancer pick? We compare the options.',
                'target_keyword' => 'best email tool',
                'suggested_tags' => ['email', 'freelancing', 'email'],
                'suggested_category' => 'Email Marketing',
                'faq' => [
                    ['question' => 'Is Acme Mailer free?', 'answer' => 'There is a free plan for up to 500 contacts.'],
                    ['question' => '', 'answer' => 'Dropped: no question.'],
                ],
            ], $meta),
        ]);
    }

    protected function fakeWordPress(WordpressSite $site, int $status = 201, array $body = []): void
    {
        Http::fake([
            rtrim($site->rest_url, '/').'/affilstack/v1/*' => Http::response($body ?: [
                'post_id' => 42,
                'status' => 'draft',
                'edit_url' => $site->site_url.'/wp-admin/post.php?post=42&action=edit',
                'preview_url' => $site->site_url.'/?p=42&preview=true',
                'updated' => false,
            ], $status),
        ]);
    }

    public function test_the_payload_carries_the_tracked_link_disclosure_seo_terms_faq_and_cover(): void
    {
        $user = $this->customer();
        $generation = $this->article($user);

        $payload = app(ArticlePayload::class)->build($generation);

        $this->assertSame('affilstack-generation-'.$generation->id, $payload['external_id']);
        $this->assertSame('Best Email Tool for Freelancers', $payload['title']);
        $this->assertSame('best-email-tool-freelancers', $payload['slug']);
        $this->assertStringNotContainsString('<h1>', $payload['content'], 'WordPress shows the title itself');
        $this->assertStringNotContainsString(Offer::LINK_PLACEHOLDER, $payload['content']);
        $this->assertStringNotContainsString('partners.acme.example', $payload['content'], 'the raw affiliate URL never leaves AffilStack');
        $this->assertMatchesRegularExpression('#<a href="[^"]+">Acme Mailer</a>#', $payload['content']);
        $this->assertStringNotContainsString('<script', $payload['content']);
        $this->assertStringContainsString('<h2>Frequently asked questions</h2>', $payload['content']);
        $this->assertStringContainsString('<h3>Is Acme Mailer free?</h3>', $payload['content']);

        $this->assertSame([
            'title' => 'Best Email Tool for Freelancers',
            'description' => 'Which email tool should a freelancer pick? We compare the options.',
            'focus_keyword' => 'best email tool',
        ], $payload['seo']);
        $this->assertSame(['Email Marketing'], $payload['categories']);
        $this->assertSame(['email', 'freelancing'], $payload['tags']);
        $this->assertSame([['question' => 'Is Acme Mailer free?', 'answer' => 'There is a free plan for up to 500 contacts.']], $payload['faq']);

        if ($payload['featured_image'] !== null) {
            $this->assertStringEndsWith('.png', $payload['featured_image']['url']);
            $this->assertSame('Best Email Tool for Freelancers', $payload['featured_image']['alt']);
        }
    }

    public function test_long_seo_titles_are_never_cut_mid_word(): void
    {
        $title = str_repeat('Freelancer email marketing tools compared ', 4);
        $payload = app(ArticlePayload::class)->build($this->article($this->customer(), ['title' => trim($title)]));

        $this->assertLessThanOrEqual(120, mb_strlen($payload['seo']['title']));
        $this->assertStringEndsWith(' ', $payload['seo']['title'].' ');
        $this->assertContains(last(explode(' ', $payload['seo']['title'])), explode(' ', $title));
    }

    public function test_send_to_wordpress_queues_the_article_for_that_site(): void
    {
        Queue::fake();
        $user = $this->customer();
        $site = WordpressSite::factory()->for($user)->create();
        $generation = $this->article($user);

        $this->actingAs($user)
            ->post(route('generations.wordpress', $generation), ['site_id' => $site->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $delivery = WordpressDelivery::sole();
        $this->assertSame('queued', $delivery->status);
        $this->assertFalse($delivery->automatic);
        $this->assertSame($user->id, $delivery->requested_by_id);
        Queue::assertPushed(SendArticleToWordPress::class, fn ($job) => $job->delivery->is($delivery));

        // Sending again reuses the same delivery (and so the same draft).
        $this->actingAs($user)->post(route('generations.wordpress', $generation), ['site_id' => $site->id]);
        $this->assertSame(1, WordpressDelivery::count());
    }

    public function test_articles_only_go_to_the_customers_own_connected_sites_on_a_plan_with_the_plugin(): void
    {
        Queue::fake();
        $user = $this->customer();
        $generation = $this->article($user);

        $someoneElses = WordpressSite::factory()->create();
        $this->actingAs($user)->post(route('generations.wordpress', $generation), ['site_id' => $someoneElses->id])->assertSessionHas('error');

        $disconnected = WordpressSite::factory()->for($user)->create(['status' => 'disconnected']);
        $this->actingAs($user)->post(route('generations.wordpress', $generation), ['site_id' => $disconnected->id])->assertSessionHas('error');

        $other = $this->customer();
        $site = WordpressSite::factory()->for($other)->create();
        $this->actingAs($other)->post(route('generations.wordpress', $generation), ['site_id' => $site->id])->assertForbidden();

        $noPlugin = $this->customer(0);
        $theirSite = WordpressSite::factory()->for($noPlugin)->create();
        $this->actingAs($noPlugin)->post(route('generations.wordpress', $this->article($noPlugin)), ['site_id' => $theirSite->id])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'plan'));

        $unfinished = $this->article($user);
        $unfinished->update(['status' => 'queued']);
        $mine = WordpressSite::factory()->for($user)->create();
        $this->actingAs($user)->post(route('generations.wordpress', $unfinished), ['site_id' => $mine->id])->assertSessionHas('error');

        $this->assertSame(0, WordpressDelivery::count());
        Queue::assertNothingPushed();
    }

    public function test_team_seats_can_send_to_their_owners_sites(): void
    {
        Queue::fake();
        $owner = $this->customer();
        $site = WordpressSite::factory()->for($owner)->create();
        $generation = $this->article($owner);
        $plan = $owner->activeSubscription->plan;
        $plan->update(['seat_mode' => 'shared', 'team_seats' => 3]);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_role' => 'member']);
        $seat->assignRole('user');

        $this->actingAs($seat->fresh())
            ->post(route('generations.wordpress', $generation), ['site_id' => $site->id])
            ->assertSessionHas('success');

        $this->assertSame($seat->id, WordpressDelivery::sole()->requested_by_id);
    }

    public function test_the_job_sends_a_signed_request_and_records_the_draft(): void
    {
        $user = $this->customer();
        $site = WordpressSite::factory()->for($user)->create(['site_url' => 'https://blog.example.com', 'rest_url' => 'https://blog.example.com/wp-json/']);
        $generation = $this->article($user);
        $delivery = WordpressDelivery::create(['wordpress_site_id' => $site->id, 'generation_id' => $generation->id, 'status' => 'queued']);
        $this->fakeWordPress($site);

        SendArticleToWordPress::dispatchSync($delivery);

        Http::assertSent(function (Request $request) use ($site) {
            return $request->url() === 'https://blog.example.com/wp-json/affilstack/v1/posts'
                && $request->method() === 'POST'
                && $request->header(ConnectorSignature::SITE_HEADER)[0] === (string) $site->id
                && ConnectorSignature::verify(
                    $site->secret,
                    $request->header(ConnectorSignature::TIMESTAMP_HEADER)[0],
                    $request->header(ConnectorSignature::SIGNATURE_HEADER)[0],
                    'POST',
                    '/affilstack/v1/posts',
                    $request->body(),
                )
                && $request['title'] === 'Best Email Tool for Freelancers'
                && $request['categories'] === ['Email Marketing'];
        });

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(42, $delivery->wp_post_id);
        $this->assertSame('draft', $delivery->wp_post_status);
        $this->assertSame('https://blog.example.com/wp-admin/post.php?post=42&action=edit', $delivery->edit_url);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNotNull($site->fresh()->last_seen_at);
    }

    public function test_links_back_from_the_plugin_must_point_at_the_same_site(): void
    {
        $user = $this->customer();
        $site = WordpressSite::factory()->for($user)->create(['site_url' => 'https://blog.example.com', 'rest_url' => 'https://blog.example.com/wp-json/']);
        $delivery = WordpressDelivery::create(['wordpress_site_id' => $site->id, 'generation_id' => $this->article($user)->id, 'status' => 'queued']);
        $this->fakeWordPress($site, 201, ['post_id' => 7, 'status' => 'draft', 'edit_url' => 'https://phish.example.net/login', 'preview_url' => 'javascript:alert(1)']);

        SendArticleToWordPress::dispatchSync($delivery);

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->edit_url);
        $this->assertNull($delivery->fresh()->preview_url);
    }

    public function test_an_article_already_published_in_wordpress_is_shown_as_published_not_failed(): void
    {
        $user = $this->customer();
        $site = WordpressSite::factory()->for($user)->create(['site_url' => 'https://blog.example.com', 'rest_url' => 'https://blog.example.com/wp-json/']);
        $generation = $this->article($user);
        $delivery = WordpressDelivery::create(['wordpress_site_id' => $site->id, 'generation_id' => $generation->id, 'status' => 'queued']);
        $this->fakeWordPress($site, 409, [
            'code' => 'affilstack_published',
            'message' => 'This article is already published on this site.',
            'data' => ['status' => 409, 'post_id' => 42, 'post_status' => 'publish', 'edit_url' => 'https://blog.example.com/wp-admin/post.php?post=42&action=edit'],
        ]);

        SendArticleToWordPress::dispatchSync($delivery);

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('publish', $delivery->wp_post_status);
        $this->assertSame(42, $delivery->wp_post_id);
        $this->assertNull($delivery->error);

        $this->actingAs($user)->get(route('offers.feature', [$generation->offer_id, 'blog']))
            ->assertOk()
            ->assertSee('Published')
            ->assertSee('left unchanged')
            ->assertSee('Edit in WordPress');
    }

    /**
     * @return array<string, array{0: int, 1: array<string, mixed>, 2: string}>
     */
    public static function failures(): array
    {
        return [
            'not connected any more' => [401, ['code' => 'affilstack_not_connected', 'message' => 'x'], 'no longer recognises this connection'],
            'plugin missing' => [404, ['code' => 'rest_no_route', 'message' => 'No route'], 'isn\'t active on this site'],
            'blocked' => [403, ['code' => 'forbidden'], 'blocked AffilStack'],
            'redirected' => [301, [], 'redirected the request'],
            'server error' => [500, ['message' => 'Database <b>gone</b>'], 'WordPress said: Database gone'],
        ];
    }

    #[DataProvider('failures')]
    public function test_failures_are_recorded_with_a_reason_the_customer_can_act_on(int $status, array $body, string $expected): void
    {
        $user = $this->customer();
        $site = WordpressSite::factory()->for($user)->create();
        $delivery = WordpressDelivery::create(['wordpress_site_id' => $site->id, 'generation_id' => $this->article($user)->id, 'status' => 'queued']);
        Http::fake(['*' => Http::response($body, $status, $status === 301 ? ['Location' => 'https://elsewhere.example.com/'] : [])]);

        SendArticleToWordPress::dispatchSync($delivery);

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertStringContainsString($expected, $delivery->error);
        $this->assertSame($delivery->error, $site->fresh()->last_error);
    }

    public function test_sites_that_now_resolve_to_a_private_address_are_never_contacted(): void
    {
        $user = $this->customer();
        $site = WordpressSite::factory()->for($user)->create();
        $delivery = WordpressDelivery::create(['wordpress_site_id' => $site->id, 'generation_id' => $this->article($user)->id, 'status' => 'queued']);
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['10.0.0.5']);
        Http::fake();

        SendArticleToWordPress::dispatchSync($delivery);

        Http::assertNothingSent();
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertStringContainsString('can\'t reach this site', $delivery->fresh()->error);
    }

    public function test_finished_articles_go_automatically_to_sites_with_auto_send_on(): void
    {
        Queue::fake([SendArticleToWordPress::class]);
        $user = $this->customer();
        $auto = WordpressSite::factory()->for($user)->create(['auto_send' => true]);
        WordpressSite::factory()->for($user)->create(['auto_send' => false]);
        WordpressSite::factory()->for($user)->create(['auto_send' => true, 'status' => 'disconnected']);

        $offer = Offer::create(['user_id' => $user->id, 'product_name' => 'Acme Mailer', 'product_url' => 'https://acme.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $generation = $offer->generations()->create(['user_id' => $user->id, 'module' => 'blog_article', 'input' => ['offer_id' => $offer->id], 'credits_spent' => 0, 'status' => 'queued']);

        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'title' => 'Great Mailer',
                'slug' => 'great-mailer',
                'meta_description' => 'A great mailer.',
                'target_keyword' => 'mailer',
                'article_markdown' => "# Great Mailer\n\nBody.",
                'suggested_tags' => ['email'],
                'suggested_category' => 'Email Marketing',
                'faq' => [['question' => 'Q?', 'answer' => 'A.']],
            ]);
        });

        (new RunBlogArticle($generation))->handle(app(BlogArticleService::class), app(WordPressDispatcher::class));

        $delivery = WordpressDelivery::sole();
        $this->assertSame($auto->id, $delivery->wordpress_site_id);
        $this->assertTrue($delivery->automatic);
        Queue::assertPushed(SendArticleToWordPress::class, 1);
        $this->assertSame('Email Marketing', $generation->fresh()->output_meta['suggested_category']);
    }

    public function test_auto_send_skips_plans_without_the_plugin(): void
    {
        Queue::fake([SendArticleToWordPress::class]);
        $user = $this->customer(0);
        WordpressSite::factory()->for($user)->create(['auto_send' => true]);

        $this->assertSame(0, app(WordPressDispatcher::class)->autoSend($this->article($user)));
        Queue::assertNothingPushed();
    }

    public function test_the_article_shows_a_send_button_and_where_each_site_stands(): void
    {
        $user = $this->customer();
        $generation = $this->article($user);
        $site = WordpressSite::factory()->for($user)->create(['name' => 'Gadget Reviews']);
        WordpressDelivery::create([
            'wordpress_site_id' => $site->id,
            'generation_id' => $generation->id,
            'status' => 'sent',
            'wp_post_status' => 'draft',
            'edit_url' => $site->site_url.'/wp-admin/post.php?post=9&action=edit',
            'sent_at' => now(),
        ]);

        $this->actingAs($user)->get(route('offers.feature', [$generation->offer_id, 'blog']))
            ->assertOk()
            ->assertSee('Send to Gadget Reviews')
            ->assertSee('Draft ready')
            ->assertSee('Edit in WordPress');
    }

    public function test_customers_without_a_site_are_pointed_to_the_setup_page(): void
    {
        $user = $this->customer();
        $generation = $this->article($user);

        $this->actingAs($user)->get(route('offers.feature', [$generation->offer_id, 'blog']))
            ->assertOk()
            ->assertSee('Connect a WordPress site to send this as a draft');
    }
}
