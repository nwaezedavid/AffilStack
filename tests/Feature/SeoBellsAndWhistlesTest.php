<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\SitePages\Pages\CreateSitePage;
use App\Filament\Resources\SitePages\Pages\EditSitePage;
use App\Models\FaqItem;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Seo\SeoMetaAssistant;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * "I want all the bells and whistles... control how my website appears on
 * search engines and social media platforms. Use RankMath for example."
 * Covers the concrete pieces actually built: per-page SEO/social overrides
 * (title, meta description, OG image, noindex) on SitePage, the AI meta
 * assistant, the sitewide Organization/WebSite JSON-LD graph, breadcrumb
 * schema, FAQPage schema on the help page, ad pixels, and social profile
 * links feeding sameAs.
 */
class SeoBellsAndWhistlesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_a_page_without_a_custom_seo_title_falls_back_to_its_regular_title(): void
    {
        $page = SitePage::create(['slug' => 'about', 'title' => 'About Us', 'content' => 'Hello', 'is_published' => true]);

        $this->assertSame('About Us', $page->displayTitle());
    }

    public function test_a_page_with_a_custom_seo_title_uses_it_instead(): void
    {
        $page = SitePage::create([
            'slug' => 'about', 'title' => 'About Us', 'seo_title' => 'About AffilStack — Our Story', 'content' => 'Hello', 'is_published' => true,
        ]);

        $this->assertSame('About AffilStack — Our Story', $page->displayTitle());
    }

    public function test_a_page_carries_its_own_seo_title_and_meta_description_on_the_public_site(): void
    {
        SitePage::create([
            'slug' => 'refund-policy',
            'title' => 'Refund Policy',
            'seo_title' => 'Refund Policy — Custom SEO Title',
            'meta_description' => 'Our refund terms explained simply.',
            'content' => 'Body copy',
            'is_published' => true,
        ]);

        $response = $this->get('/refund-policy');

        $response->assertOk();
        $response->assertSee('Refund Policy — Custom SEO Title', false);
        $response->assertSee('Our refund terms explained simply.', false);
    }

    public function test_a_noindex_page_renders_a_noindex_robots_meta_tag(): void
    {
        SitePage::create(['slug' => 'cookie-policy', 'title' => 'Cookies', 'content' => 'Body', 'is_published' => true, 'no_index' => true]);

        $response = $this->get('/cookie-policy');

        $response->assertOk();
        $response->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_a_normal_page_does_not_render_a_robots_meta_tag(): void
    {
        SitePage::create(['slug' => 'cookie-policy', 'title' => 'Cookies', 'content' => 'Body', 'is_published' => true, 'no_index' => false]);

        $response = $this->get('/cookie-policy');

        $response->assertOk();
        $response->assertDontSee('name="robots"', false);
    }

    public function test_a_noindex_page_is_excluded_from_the_sitemap(): void
    {
        SitePage::create(['slug' => 'thank-you', 'title' => 'Thanks', 'content' => 'Body', 'is_published' => true, 'no_index' => true]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertDontSee('/thank-you', false);
    }

    public function test_a_published_page_appears_in_the_sitemap_with_a_lastmod_date(): void
    {
        $page = SitePage::create(['slug' => 'cookie-policy', 'title' => 'Cookies', 'content' => 'Body', 'is_published' => true]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(url('/cookie-policy'), false);
        $response->assertSee('<lastmod>'.$page->updated_at->toAtomString().'</lastmod>', false);
    }

    public function test_every_marketing_page_carries_the_sitewide_organization_and_website_json_ld(): void
    {
        SiteSetting::set('site_name', 'AffilStack');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"WebSite"', false);
        $response->assertSee('AffilStack', false);
    }

    public function test_the_organization_schema_includes_sames_from_configured_social_profiles(): void
    {
        SiteSetting::set('seo_social_facebook', 'https://facebook.com/affilstack');
        SiteSetting::set('seo_social_linkedin', 'https://linkedin.com/company/affilstack');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('https://facebook.com/affilstack', false);
        $response->assertSee('https://linkedin.com/company/affilstack', false);
    }

    public function test_the_organization_schema_never_fabricates_sames_for_unset_profiles(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('"sameAs":[]', false);
    }

    public function test_a_static_page_renders_breadcrumb_navigation_and_matching_schema(): void
    {
        SitePage::create(['slug' => 'terms', 'title' => 'Terms of Service', 'content' => 'Body', 'is_published' => true]);

        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('Terms of Service', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_the_help_page_carries_faq_page_schema_once_faqs_are_published(): void
    {
        FaqItem::create(['question' => 'How do I cancel?', 'answer' => 'From Billing.', 'category' => 'billing', 'is_published' => true, 'sort_order' => 1]);

        $response = $this->get('/help');

        $response->assertOk();
        $response->assertSee('"@type":"FAQPage"', false);
        $response->assertSee('How do I cancel?', false);
    }

    public function test_the_help_page_omits_faq_page_schema_when_there_are_no_published_faqs(): void
    {
        $response = $this->get('/help');

        $response->assertOk();
        $response->assertDontSee('FAQPage', false);
    }

    public function test_admin_can_save_ad_pixel_ids_and_social_profile_links(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SeoSettings::class)
            ->fillForm([
                'seo_meta_pixel_id' => '123456789012345',
                'seo_tiktok_pixel_id' => 'CXXXXXXXXXXXXXXXXXXX',
                'seo_social_facebook' => 'https://facebook.com/affilstack',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('123456789012345', SiteSetting::get('seo_meta_pixel_id'));
        $this->assertSame('CXXXXXXXXXXXXXXXXXXX', SiteSetting::get('seo_tiktok_pixel_id'));
        $this->assertSame('https://facebook.com/affilstack', SiteSetting::get('seo_social_facebook'));
    }

    public function test_the_meta_and_tiktok_pixels_render_on_the_public_homepage_once_configured(): void
    {
        SiteSetting::set('seo_meta_pixel_id', '123456789012345');
        SiteSetting::set('seo_tiktok_pixel_id', 'CXXXXXXXXXXXXXXXXXXX');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('connect.facebook.net', false);
        $response->assertSee('analytics.tiktok.com', false);
    }

    public function test_pixels_are_absent_when_not_configured(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('connect.facebook.net', false);
        $response->assertDontSee('analytics.tiktok.com', false);
    }

    public function test_seo_meta_assistant_returns_an_ai_drafted_title_and_description(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andReturn([
                'seo_title' => 'Best Affiliate Tools — AffilStack',
                'meta_description' => 'Everything affiliates need in one dashboard.',
            ]);
        });

        $result = app(SeoMetaAssistant::class)->suggest('Home', 'AffilStack helps affiliates manage offers.', 'affiliate tools');

        $this->assertSame('Best Affiliate Tools — AffilStack', $result['seo_title']);
        $this->assertSame('Everything affiliates need in one dashboard.', $result['meta_description']);
    }

    public function test_seo_meta_assistant_wraps_a_provider_failure_in_a_friendly_message(): void
    {
        $this->mock(AIProvider::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateJson')->once()->andThrow(new AIGenerationException('rate limited'));
        });

        $this->expectException(AIGenerationException::class);
        $this->expectExceptionMessage('Could not reach the AI assistant right now — please try again.');

        app(SeoMetaAssistant::class)->suggest('Home', 'content', null);
    }

    public function test_admin_can_set_per_page_seo_fields_and_noindex_from_filament(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateSitePage::class)
            ->fillForm([
                'title' => 'Shipping Policy',
                'slug' => 'shipping-policy',
                'content' => 'We ship worldwide.',
                'is_published' => true,
                'no_index' => true,
                'focus_keyword' => 'shipping policy',
                'seo_title' => 'Shipping Policy — AffilStack',
                'meta_description' => 'Learn about our shipping timelines and costs.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = SitePage::where('slug', 'shipping-policy')->first();
        $this->assertNotNull($page);
        $this->assertTrue($page->no_index);
        $this->assertSame('Shipping Policy — AffilStack', $page->seo_title);
    }

    public function test_admin_can_edit_an_existing_pages_seo_fields(): void
    {
        $page = SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Body', 'is_published' => true]);

        Livewire::actingAs($this->admin)
            ->test(EditSitePage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['seo_title' => 'About Us — The Real Story', 'no_index' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('About Us — The Real Story', $page->seo_title);
        $this->assertTrue($page->no_index);
    }
}
