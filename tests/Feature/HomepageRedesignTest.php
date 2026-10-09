<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePanelLoginCompleted;
use App\Models\AgentTask;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The redesigned homepage: admin-ordered, individually switchable sections
 * (Content → Homepage), editable copy with genuine defaults, the YouTube
 * "See AffilStack in action" section, and the accessible header/footer.
 */
class HomepageRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_full_default_journey(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Skip to content')
            ->assertSee('id="main"', false)
            ->assertSee('id="how-it-works"', false)
            ->assertSee('Research a product')
            ->assertSee('Find buyers on Maps &amp; LinkedIn', false)
            ->assertSee('Launch AI campaigns &amp; content', false)
            ->assertSee('Track clicks &amp; earnings', false)
            ->assertSee('Built to replace your whole affiliate stack')
            ->assertSee('Ready to find your next sale?')
            ->assertSee('aria-controls="mobile-menu"', false);
    }

    public function test_hero_copy_comes_from_settings_with_gold_highlighting_and_escaping(): void
    {
        SiteSetting::set('hero_headline', 'Sell more. *Earn more.* <script>alert(1)</script>');
        SiteSetting::set('hero_subheadline', 'Custom subheadline for Lagos.');
        SiteSetting::set('home_hero_primary_label', 'Start today');
        SiteSetting::set('home_hero_primary_url', '/pricing?ref=hero');

        $response = $this->get('/')->assertOk();

        $response->assertSee('<span class="bg-gradient-to-r from-gold-300 via-amber-200 to-gold-400 bg-clip-text text-transparent">Earn more.</span>', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;', false);
        $response->assertSee('Custom subheadline for Lagos.');
        $response->assertSee('Start today');
        $response->assertSee('href="/pricing?ref=hero"', false);
    }

    public function test_an_unsafe_button_link_falls_back_to_pricing(): void
    {
        SiteSetting::set('home_hero_primary_url', 'javascript:alert(1)');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false)
            ->assertSee('href="'.route('registration.pricing').'"', false);
    }

    public function test_sections_follow_the_admins_order_and_can_be_hidden(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'is_active' => true, 'sort_order' => 1]);
        SiteSetting::set('home_sections', json_encode([
            ['key' => 'features', 'enabled' => true],
            ['key' => 'how_it_works', 'enabled' => true],
            ['key' => 'pricing', 'enabled' => false],
            ['key' => 'stats', 'enabled' => false],
        ]));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'id="how-it-works"'), strpos($html, 'id="features"'));
        $this->assertStringNotContainsString('Simple, transparent pricing', $html);
        $this->assertStringNotContainsString('Built to replace your whole affiliate stack', $html);
        // Sections missing from the saved list still render (appended, on).
        $this->assertStringContainsString('Ready to find your next sale?', $html);
    }

    public function test_the_video_section_appears_once_any_youtube_link_is_saved(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="see-it-in-action"', false);

        SiteSetting::set('home_video_url', 'https://youtu.be/dQw4w9WgXcQ?si=share');

        $this->get('/')
            ->assertOk()
            ->assertSee('id="see-it-in-action"', false)
            ->assertSee('See AffilStack in action')
            ->assertSee('data-youtube-facade', false)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false);
    }

    public function test_an_unrecognisable_video_link_keeps_the_section_hidden(): void
    {
        SiteSetting::set('home_video_url', 'https://vimeo.com/12345');

        $this->get('/')->assertOk()->assertDontSee('id="see-it-in-action"', false);
    }

    public function test_the_hero_can_show_a_youtube_video_instead_of_the_product_preview(): void
    {
        SiteSetting::set('hero_media_type', 'youtube');
        SiteSetting::set('hero_youtube_url', 'https://www.youtube.com/shorts/dQw4w9WgXcQ');

        $this->get('/')
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('Illustration of the AffilStack dashboard', false);
    }

    public function test_custom_steps_and_stats_replace_the_defaults(): void
    {
        SiteSetting::set('home_steps', json_encode([
            ['icon' => 'rocket-launch', 'title' => 'Pick a winner', 'description' => 'Choose your product.'],
            ['icon' => 'banknotes', 'title' => 'Collect commission', 'description' => 'Get paid.'],
        ]));
        SiteSetting::set('home_stats', json_encode([
            ['value' => '5,000+', 'label' => 'affiliates across Africa'],
            ['value' => '$1.2M', 'label' => 'commissions tracked'],
        ]));

        $this->get('/')
            ->assertOk()
            ->assertSee('Pick a winner')
            ->assertSee('Collect commission')
            ->assertDontSee('Research a product')
            ->assertSee('5,000+')
            ->assertSee('commissions tracked')
            ->assertDontSee('dashboard instead of a dozen separate tools');
    }

    public function test_the_faq_teaser_shows_published_questions_only(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="faq"', false);

        FaqItem::create(['question' => 'Can I pay in naira?', 'answer' => 'Yes, at checkout.', 'category' => 'billing', 'is_published' => true, 'sort_order' => 1]);
        FaqItem::create(['question' => 'Secret draft question?', 'answer' => 'Hidden.', 'category' => 'billing', 'is_published' => false, 'sort_order' => 2]);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="faq"', false)
            ->assertSee('Can I pay in naira?')
            ->assertDontSee('Secret draft question?')
            ->assertDontSee('FAQPage', false);
    }

    public function test_the_announcement_bar_renders_with_a_safe_link_only(): void
    {
        SiteSetting::set('header_announcement', 'Webinar this Friday');
        SiteSetting::set('header_announcement_link_label', 'Save my seat');
        SiteSetting::set('header_announcement_link_url', '/learn');

        $this->get('/')->assertOk()->assertSee('Webinar this Friday')->assertSee('Save my seat')->assertSee('href="/learn"', false);

        SiteSetting::set('header_announcement_link_url', 'javascript:alert(1)');

        $this->get('/')->assertOk()->assertSee('Webinar this Friday')->assertDontSee('Save my seat')->assertDontSee('javascript:alert(1)', false);
    }

    public function test_emoji_feature_icons_from_before_the_redesign_still_render(): void
    {
        HomepageFeature::create(['title' => 'Legacy card', 'description' => 'Old emoji icon.', 'icon' => '🔗', 'media_type' => 'none', 'is_active' => true]);
        HomepageFeature::create(['title' => 'Modern card', 'description' => 'Heroicon.', 'icon' => 'map-pin', 'media_type' => 'none', 'is_active' => true]);

        $this->get('/')->assertOk()->assertSee('🔗')->assertSee('Legacy card')->assertSee('Modern card');
    }

    public function test_tony_branding_previews_still_override_the_new_hero(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        SiteSetting::set('hero_headline', 'Live headline');

        $task = AgentTask::create([
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => 'branding',
            'title' => 'Branding',
            'summary' => 'A brief.',
            'payload' => ['target_id' => null, 'fields' => ['hero_headline' => 'Draft *headline*', 'header_announcement' => 'Draft announcement']],
            'risk_level' => 'low',
            'status' => AgentTask::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->withSession([EnsurePanelLoginCompleted::SESSION_KEY => $admin->id])
            ->get(route('creative-tasks.preview', $task))
            ->assertOk()
            ->assertSee('Draft')
            ->assertSee('headline</span>', false)
            ->assertSee('Draft announcement')
            ->assertDontSee('Live headline');

        $this->assertSame('Live headline', SiteSetting::get('hero_headline'));
    }

    public function test_other_marketing_pages_share_the_new_header_and_footer(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('Skip to content')
            ->assertSee('href="'.route('home').'#how-it-works"', false)
            ->assertSee('Site footer');
    }
}
