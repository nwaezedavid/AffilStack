<?php

namespace Tests\Feature;

use App\Filament\Pages\HomepageEditor;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Blog\BlogFeed;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Add a section where some of our blog articles will show up for visitors
 * to read (this will be connected to the blog sub-domain)." Posts come from
 * the WordPress REST API, are cached, sanitised, and can never break the
 * homepage.
 */
class HomepageBlogFeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function wordpressPosts(int $count = 3): array
    {
        return collect(range(1, $count))->map(fn (int $i) => [
            'id' => $i,
            'date' => '2026-09-2'.$i.'T10:00:00',
            'date_gmt' => '2026-09-2'.$i.'T09:00:00',
            'link' => 'https://blog.affilstack.com/post-'.$i.'/',
            'title' => ['rendered' => 'Post &#8220;'.$i.'&#8221; &amp; <em>friends</em>'],
            'excerpt' => ['rendered' => '<p>How to find buyers on Google Maps&hellip; <script>alert("x")</script> [&hellip;]</p>'],
            '_embedded' => [
                'author' => [['name' => 'Nwaeze David']],
                'wp:featuredmedia' => [[
                    'source_url' => 'https://blog.affilstack.com/wp-content/uploads/'.$i.'.jpg',
                    'alt_text' => 'Cover '.$i,
                    'media_details' => ['sizes' => ['medium_large' => ['source_url' => 'https://blog.affilstack.com/wp-content/uploads/'.$i.'-768.jpg']]],
                ]],
                'wp:term' => [[
                    ['taxonomy' => 'category', 'slug' => 'uncategorized', 'name' => 'Uncategorized'],
                    ['taxonomy' => 'category', 'slug' => 'playbooks', 'name' => 'Playbooks'],
                ], [
                    ['taxonomy' => 'post_tag', 'slug' => 'maps', 'name' => 'maps'],
                ]],
            ],
        ])->all();
    }

    public function test_no_request_is_made_and_no_section_shows_until_a_blog_is_configured(): void
    {
        Http::fake();

        $this->get('/')->assertOk()->assertDontSee('id="blog"', false)->assertDontSee('Visit our blog');

        Http::assertNothingSent();
    }

    public function test_latest_posts_render_sanitised_with_images_and_a_blog_link(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com/');
        SiteSetting::set('blog_feed_title', 'Read the playbooks');
        Http::fake(['blog.affilstack.com/wp-json/wp/v2/posts*' => Http::response($this->wordpressPosts())]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('id="blog"', false)
            ->assertSee('Read the playbooks')
            ->assertSee('Post “1” &amp; friends', false)
            ->assertDontSee('<em>friends</em>', false)
            ->assertSee('How to find buyers on Google Maps…')
            ->assertDontSee('alert(', false)
            ->assertDontSee('[&hellip;]', false)
            ->assertSee('https://blog.affilstack.com/wp-content/uploads/1-768.jpg', false)
            ->assertSee('alt="Cover 1"', false)
            ->assertSee('Playbooks')
            ->assertDontSee('Uncategorized')
            ->assertSee('Nwaeze David')
            ->assertSee('https://blog.affilstack.com/post-3/', false)
            // "Visit our blog" link in the section and footer, "Blog" in the nav.
            ->assertSee('Visit our blog');

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://blog.affilstack.com/wp-json/wp/v2/posts?per_page=3&_embed=1'));
    }

    public function test_posts_are_cached_so_the_blog_is_asked_once_per_hour(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        Http::fake(['*' => Http::response($this->wordpressPosts())]);

        $this->get('/')->assertOk();
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        Http::assertSentCount(1);

        $this->travel(61)->minutes();
        $this->get('/')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_the_admins_post_count_is_requested_and_clamped(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        SiteSetting::set('blog_feed_count', '50');
        Http::fake(['*' => Http::response($this->wordpressPosts(6))]);

        $this->get('/')->assertOk()->assertSee('Post “6”', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'per_page=6'));
    }

    public function test_an_unreachable_blog_hides_the_section_logs_a_warning_and_never_breaks_the_page(): void
    {
        Log::spy();
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        Http::fake(['*' => Http::response('Bad gateway', 502)]);

        $this->get('/')->assertOk()->assertDontSee('id="blog"', false)->assertSee('Ready to find your next sale?');

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'blog feed unavailable'))->once();

        // The failure is remembered briefly instead of retried on every view.
        $this->get('/')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_a_timeout_is_handled_gracefully(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->get('/')->assertOk()->assertDontSee('id="blog"', false);
    }

    public function test_a_site_without_pretty_permalinks_is_read_through_rest_route(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        Http::fake([
            'blog.affilstack.com/wp-json/*' => Http::response('Not found', 404),
            'blog.affilstack.com/?rest_route=*' => Http::response($this->wordpressPosts(3)),
        ]);

        $this->get('/')->assertOk()->assertSee('id="blog"', false);
    }

    public function test_a_non_wordpress_response_hides_the_section(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        Http::fake(['*' => Http::response('<html>Hello</html>', 200)]);

        $this->get('/')->assertOk()->assertDontSee('id="blog"', false);
    }

    public function test_the_section_can_be_switched_off(): void
    {
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        SiteSetting::setFlag('blog_feed_enabled', false);
        Http::fake();

        $this->get('/')->assertOk()->assertDontSee('id="blog"', false);

        Http::assertNothingSent();
    }

    public function test_the_seo_blog_url_is_used_when_the_feed_has_no_address_of_its_own(): void
    {
        SiteSetting::set('seo_blog_url', 'https://blog.affilstack.com/');
        Http::fake(['*' => Http::response($this->wordpressPosts())]);

        $this->get('/')->assertOk()->assertSee('id="blog"', false);
    }

    public function test_private_network_addresses_are_never_fetched(): void
    {
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['127.0.0.1']);
        SiteSetting::set('blog_feed_url', 'https://blog.internal');
        Http::fake();

        $this->get('/')->assertOk()->assertDontSee('id="blog"', false);

        Http::assertNothingSent();
    }

    public function test_the_base_url_is_normalised(): void
    {
        $this->assertSame('https://blog.affilstack.com', BlogFeed::normalizeBaseUrl('blog.affilstack.com/'));
        $this->assertSame('https://blog.affilstack.com', BlogFeed::normalizeBaseUrl('https://blog.affilstack.com/wp-json/wp/v2/posts?per_page=3'));
        $this->assertNull(BlogFeed::normalizeBaseUrl(''));
    }

    public function test_the_admin_test_connection_action_reports_how_many_posts_were_found(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Http::fake(['*' => Http::response($this->wordpressPosts(3))]);

        Livewire::actingAs($admin)
            ->test(HomepageEditor::class)
            ->set('data.blog_feed_url', 'https://blog.affilstack.com')
            ->call('testBlogConnection')
            ->assertNotified('Blog connected — 3 posts found');
    }

    public function test_the_admin_test_connection_action_explains_a_failure(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Http::fake(['*' => Http::response('Oops', 500)]);

        Livewire::actingAs($admin)
            ->test(HomepageEditor::class)
            ->set('data.blog_feed_url', 'https://blog.affilstack.com')
            ->call('testBlogConnection')
            ->assertNotified('Could not read the blog');
    }

    public function test_saving_the_homepage_editor_flushes_cached_posts(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        SiteSetting::set('blog_feed_url', 'https://blog.affilstack.com');
        Http::fake(['*' => Http::response($this->wordpressPosts())]);

        $this->get('/')->assertOk();
        Http::assertSentCount(1);

        Livewire::actingAs($admin)->test(HomepageEditor::class)->call('save')->assertHasNoFormErrors();

        $this->get('/')->assertOk();
        Http::assertSentCount(2);
    }
}
