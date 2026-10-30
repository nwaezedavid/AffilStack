<?php

namespace Tests\Feature;

use App\Filament\Pages\BlogSettings;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Models\BlogPost;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\BlogNewsletterConfirmation;
use App\Services\AI\AIProvider;
use App\Services\Blog\BlogKit;
use App\Services\Blog\WordPressPublisher;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Feature 2: the WordPress blog on blog.affilstack.com — the theme's kit
 * (synced header/footer, CTA box, newsletter), double opt-in newsletter
 * sign-ups into the CRM, and publishing posts from AffilStack's admin.
 */
class BlogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['93.184.216.34']);
    }

    protected function tearDown(): void
    {
        OutboundUrlGuard::resolveUsing(null);

        parent::tearDown();
    }

    public function test_the_kit_gives_the_theme_the_main_sites_menu_footer_and_boxes(): void
    {
        SiteSetting::set('blog_cta_heading', 'Grow with AffilStack');

        $kit = $this->getJson('/api/blog/kit')->assertOk()->json();

        $this->assertSame('AffilStack', $kit['site']['name']);
        $this->assertSame(route('home'), $kit['site']['url']);
        $this->assertSame(route('login'), $kit['header']['login']['url']);
        $this->assertSame(route('registration.pricing'), $kit['header']['cta']['url']);
        $this->assertNotEmpty($kit['header']['tools']);
        $this->assertNotEmpty($kit['footer']['columns']);
        $this->assertSame('Grow with AffilStack', $kit['cta_box']['heading']);
        $this->assertFalse($kit['newsletter']['enabled']);
        $this->assertStringNotContainsString('afsblog_', json_encode($kit));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $kit['version']);

        foreach ($kit['footer']['columns'] as $column) {
            foreach ($column['links'] as $link) {
                $this->assertMatchesRegularExpression('#^https?://#', $link['url']);
            }
        }
    }

    public function test_newsletter_sign_ups_need_the_connection_key_and_a_list(): void
    {
        $this->postJson('/api/blog/subscribe', ['email' => 'reader@example.com'])->assertUnauthorized();
        $this->postJson('/api/blog/subscribe', ['email' => 'reader@example.com'], ['X-AffilStack-Blog-Key' => 'wrong'])->assertUnauthorized();

        $this->postJson('/api/blog/subscribe', ['email' => 'reader@example.com'], $this->keyHeader())->assertStatus(409);
    }

    public function test_a_reader_joins_the_crm_list_only_after_confirming_their_email(): void
    {
        Notification::fake();
        $list = $this->newsletterList();

        $this->postJson('/api/blog/subscribe', [
            'email' => 'Reader@Example.com',
            'name' => 'Ngozi',
            'source_url' => 'https://blog.affilstack.com/how-to-find-buyers/',
        ], $this->keyHeader())->assertOk();

        $this->assertSame(0, CrmContact::count());

        $confirmUrl = null;
        Notification::assertSentOnDemand(BlogNewsletterConfirmation::class, function (BlogNewsletterConfirmation $notification, array $channels, object $notifiable) use (&$confirmUrl): bool {
            $confirmUrl = $notification->confirmUrl;

            return $notifiable->routes['mail'] === 'reader@example.com';
        });

        $this->get(str_replace(url('/'), '', $confirmUrl).'x')->assertForbidden();
        $this->get($confirmUrl)->assertRedirect('https://blog.affilstack.com/?subscribed=1');

        $contact = CrmContact::sole();
        $this->assertSame('reader@example.com', $contact->email);
        $this->assertSame('Ngozi', $contact->name);
        $this->assertSame('blog', $contact->source);
        $this->assertSame($list->user_id, $contact->user_id);
        $this->assertTrue($list->contacts()->whereKey($contact->id)->exists());

        // Confirming twice doesn't duplicate anything.
        $this->get($confirmUrl)->assertRedirect();
        $this->assertSame(1, CrmContact::count());
    }

    public function test_the_sign_up_form_cant_flood_someones_inbox(): void
    {
        Notification::fake();
        $this->newsletterList();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/blog/subscribe', ['email' => 'victim@example.com'], $this->keyHeader())->assertOk();
        }

        Notification::assertSentOnDemandTimes(BlogNewsletterConfirmation::class, 3);
    }

    public function test_publishing_creates_then_updates_the_same_wordpress_post(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blog-posts/cover.webp', 'image-bytes');
        $this->connectWordPress();

        Http::fake([
            'blog.affilstack.com/wp-json/wp/v2/categories?*' => Http::response([['id' => 7, 'name' => 'Affiliate marketing']]),
            'blog.affilstack.com/wp-json/wp/v2/tags?*' => Http::response([]),
            'blog.affilstack.com/wp-json/wp/v2/tags' => Http::response(['id' => 21, 'name' => 'research'], 201),
            'blog.affilstack.com/wp-json/wp/v2/media' => Http::response(['id' => 55], 201),
            'blog.affilstack.com/wp-json/wp/v2/posts' => Http::response(['id' => 901, 'status' => 'draft', 'link' => 'https://blog.affilstack.com/?p=901'], 201),
            'blog.affilstack.com/wp-json/wp/v2/posts/901' => Http::response(['id' => 901, 'status' => 'publish', 'link' => 'https://blog.affilstack.com/find-buyers/']),
        ]);

        $post = BlogPost::factory()->create([
            'title' => 'Find buyers on Google Maps',
            'featured_image_path' => 'blog-posts/cover.webp',
            'seo_title' => 'Find buyers on Google Maps',
            'seo_description' => 'A practical guide.',
        ]);

        app(WordPressPublisher::class)->push($post, 'draft');
        $this->assertSame(901, $post->fresh()->wp_post_id);
        $this->assertSame('draft', $post->fresh()->wp_status);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://blog.affilstack.com/wp-json/wp/v2/posts') {
                return false;
            }

            return $request['status'] === 'draft'
                && $request['categories'] === [7]
                && $request['tags'] === [21]
                && $request['featured_media'] === 55
                && $request['meta']['rank_math_title'] === 'Find buyers on Google Maps'
                && $request->hasHeader('Authorization');
        });

        app(WordPressPublisher::class)->push($post->fresh(), 'publish');
        $this->assertSame('publish', $post->fresh()->wp_status);
        $this->assertSame('https://blog.affilstack.com/find-buyers/', $post->fresh()->wp_url);

        // The unchanged image isn't uploaded again.
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/wp/v2/media')));
    }

    public function test_a_refused_login_is_explained_and_remembered_on_the_post(): void
    {
        $this->connectWordPress();
        Http::fake(['*' => Http::response(['code' => 'rest_cannot_create', 'message' => 'Sorry, you are not allowed.'], 401)]);
        $post = BlogPost::factory()->create(['categories' => [], 'tags' => []]);

        try {
            app(WordPressPublisher::class)->push($post, 'draft');
            $this->fail('Expected the push to fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('WordPress refused the login', $e->getMessage());
        }

        $this->assertStringContainsString('WordPress refused the login', (string) $post->fresh()->last_error);
    }

    public function test_switched_off_application_passwords_are_explained(): void
    {
        $this->connectWordPress();
        Http::fake(['*' => Http::response(['code' => 'rest_not_logged_in', 'message' => 'You are not currently logged in.'], 401)]);

        try {
            app(WordPressPublisher::class)->test();
            $this->fail('Expected the test to fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('application passwords are switched off', $e->getMessage());
        }
    }

    public function test_the_blog_page_creates_the_newsletter_list_in_the_admins_crm(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);

        Livewire::actingAs($admin)
            ->test(BlogSettings::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('blog_newsletter_list_id'), ['name' => 'Blog subscribers'])
            ->assertHasNoActionErrors();

        $list = CrmList::where('user_id', $admin->id)->where('name', 'Blog subscribers')->first();
        $this->assertNotNull($list);
    }

    public function test_the_blog_settings_page_saves_and_encrypts_the_wordpress_login(): void
    {
        $admin = $this->admin();
        $list = CrmList::create(['user_id' => $admin->id, 'name' => 'Blog readers']);

        Livewire::actingAs($admin)
            ->test(BlogSettings::class)
            ->assertSee('Connection key for the AffilStack theme')
            ->fillForm([
                'seo_blog_url' => 'https://blog.affilstack.com/',
                'blog_wp_username' => 'nwaeze',
                'blog_wp_app_password' => 'abcd efgh ijkl mnop',
                'blog_newsletter_enabled' => true,
                'blog_newsletter_list_id' => $list->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://blog.affilstack.com', SiteSetting::get('seo_blog_url'));
        $this->assertNotSame('abcd efgh ijkl mnop', SiteSetting::get('blog_wp_app_password'));
        $this->assertSame('abcd efgh ijkl mnop', WordPressPublisher::applicationPassword());
        $this->assertTrue(BlogKit::newsletterEnabled());

        Http::fake(['blog.affilstack.com/wp-json/wp/v2/users/me*' => Http::response(['name' => 'Nwaeze', 'capabilities' => ['publish_posts' => true]])]);

        Livewire::actingAs($admin)->test(BlogSettings::class)->call('testConnection')->assertNotified('Connected');
    }

    public function test_admin_can_draft_with_ai_and_publish_from_the_edit_page(): void
    {
        $admin = $this->admin();
        $ai = Mockery::mock(AIProvider::class);
        $ai->shouldReceive('generateJson')->once()->andReturn([
            'title' => 'Five ways to find buyers',
            'content_html' => '<h2>Start local</h2><p>Search Google Maps.</p><script>alert(1)</script>',
            'excerpt' => 'Practical steps.',
            'focus_keyword' => 'find buyers',
            'seo_title' => 'Five ways to find buyers',
            'seo_description' => 'Practical steps to find buyers.',
            'categories' => ['Affiliate marketing'],
            'tags' => ['buyers', 'google maps'],
        ]);
        $this->app->instance(AIProvider::class, $ai);

        Livewire::actingAs($admin)
            ->test(CreateBlogPost::class)
            ->callAction('draftWithAi', ['topic' => 'Finding buyers'])
            ->assertFormSet(['title' => 'Five ways to find buyers', 'focus_keyword' => 'find buyers'])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = BlogPost::sole();
        $this->assertStringNotContainsString('<script', (string) $post->content);
        $this->assertSame($admin->id, $post->created_by);

        $this->connectWordPress();
        Http::fake([
            'blog.affilstack.com/wp-json/wp/v2/categories*' => Http::response([['id' => 3, 'name' => 'Affiliate marketing']]),
            'blog.affilstack.com/wp-json/wp/v2/tags?*' => Http::response([]),
            'blog.affilstack.com/wp-json/wp/v2/tags' => Http::response(['id' => 9], 201),
            'blog.affilstack.com/wp-json/wp/v2/posts' => Http::response(['id' => 12, 'status' => 'publish', 'link' => 'https://blog.affilstack.com/five-ways/'], 201),
        ]);

        Livewire::actingAs($admin)
            ->test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->callAction('publish')
            ->assertNotified('Published on the blog');

        $this->assertSame('publish', $post->fresh()->wp_status);
    }

    protected function newsletterList(): CrmList
    {
        $owner = $this->admin();
        $list = CrmList::create(['user_id' => $owner->id, 'name' => 'Blog readers']);

        SiteSetting::set('seo_blog_url', 'https://blog.affilstack.com');
        SiteSetting::setFlag('blog_newsletter_enabled', true);
        SiteSetting::set('blog_newsletter_list_id', (string) $list->id);

        return $list;
    }

    protected function connectWordPress(): void
    {
        SiteSetting::set('seo_blog_url', 'https://blog.affilstack.com');
        SiteSetting::set('blog_wp_username', 'nwaeze');
        WordPressPublisher::storeApplicationPassword('abcd efgh ijkl mnop');
    }

    /**
     * @return array<string, string>
     */
    protected function keyHeader(): array
    {
        return ['X-AffilStack-Blog-Key' => BlogKit::connectionKey()];
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
