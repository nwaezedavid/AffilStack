<?php

namespace Tests\Feature;

use App\Models\PageSeo;
use App\Models\Plan;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Services\Seo\OrganizationSchema;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Per-page SEO on the public site: what SeoHeadBuilder prints into the
 * marketing layout's <head> for route-based pages (PageSeo) and Site Pages,
 * the JSON-LD graph (always valid, never duplicated), robots.txt and the
 * sitemap.
 */
class SeoPageMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        SiteSetting::setFlag('affiliate_program_enabled', true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function jsonLdBlocks(TestResponse $response): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $matches);

        return array_map(function (string $json) {
            $decoded = json_decode(trim($json), true);
            $this->assertIsArray($decoded, 'JSON-LD block is not valid JSON: '.$json);

            return $decoded;
        }, $matches[1]);
    }

    /**
     * Every node from every JSON-LD block on the page, flattened.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function jsonLdNodes(TestResponse $response): array
    {
        $nodes = [];

        foreach ($this->jsonLdBlocks($response) as $block) {
            foreach ($block['@graph'] ?? [$block] as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function nodesOfType(TestResponse $response, string $type): array
    {
        return array_values(array_filter($this->jsonLdNodes($response), fn (array $node) => ($node['@type'] ?? null) === $type));
    }

    protected function headOf(TestResponse $response): string
    {
        $content = $response->getContent();

        return substr($content, 0, (int) strpos($content, '</head>'));
    }

    public function test_the_homepage_head_has_canonical_social_tags_favicons_and_a_valid_schema_graph(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $head = $this->headOf($response);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/').'">', $head);
        $this->assertStringContainsString('property="og:url" content="'.url('/').'"', $head);
        $this->assertStringContainsString('<link rel="icon" href="'.url('/favicon.ico').'?v=', $head);
        $this->assertStringContainsString('sizes="48x48" href="'.url('/favicon-48x48.png').'?v=', $head);
        $this->assertStringContainsString('rel="apple-touch-icon" sizes="180x180"', $head);
        $this->assertStringContainsString('<link rel="manifest" href="'.url('/site.webmanifest').'?v=', $head);
        $this->assertStringContainsString('name="theme-color"', $head);

        $this->assertCount(1, $this->nodesOfType($response, 'Organization'));
        $this->assertCount(1, $this->nodesOfType($response, 'WebSite'));
        $webPage = $this->nodesOfType($response, 'WebPage')[0];
        $this->assertSame(url('/').'#webpage', $webPage['@id']);
        $this->assertSame(['@id' => route('home').'#website'], $webPage['isPartOf']);
    }

    public function test_page_seo_overrides_title_description_keywords_social_and_robots_on_a_route_page(): void
    {
        SiteSetting::set('seo_twitter_handle', 'affilstack');
        SiteSetting::set('seo_facebook_app_id', '1234567890');

        PageSeo::create([
            'route_name' => 'registration.pricing',
            'seo_title' => 'Plans & pricing for affiliate marketers · %site%',
            'meta_description' => 'Compare AffilStack plans: research, AI content, UGC video and a built-in CRM from one subscription. Cancel anytime, 14-day refund.',
            'focus_keyword' => 'affiliate marketing software pricing',
            'secondary_keywords' => ['affiliate tools cost', 'AffilStack plans'],
            'tags' => ['pricing', 'plans'],
            'canonical_url' => 'https://affilstack.com/pricing',
            'robots_index' => false,
            'robots_follow' => false,
            'og_title' => 'Every AffilStack plan, side by side',
        ]);

        $response = $this->get('/pricing');

        $response->assertOk();
        $head = $this->headOf($response);
        $this->assertStringContainsString('<title>Plans &amp; pricing for affiliate marketers · AffilStack</title>', $head);
        $this->assertStringContainsString('name="description" content="Compare AffilStack plans:', $head);
        $this->assertStringContainsString('name="keywords" content="affiliate marketing software pricing, affiliate tools cost, AffilStack plans, pricing, plans"', $head);
        $this->assertStringContainsString('<link rel="canonical" href="https://affilstack.com/pricing">', $head);
        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $head);
        $this->assertStringContainsString('property="og:title" content="Every AffilStack plan, side by side"', $head);
        // X falls back to the Open Graph title when no X-specific one is set.
        $this->assertStringContainsString('name="twitter:title" content="Every AffilStack plan, side by side"', $head);
        $this->assertStringContainsString('name="twitter:site" content="@affilstack"', $head);
        $this->assertStringContainsString('property="fb:app_id" content="1234567890"', $head);
    }

    public function test_a_route_page_without_overrides_keeps_its_built_in_title_and_has_no_robots_tag(): void
    {
        $response = $this->get('/help');

        $response->assertOk();
        $this->assertStringContainsString('<title>Help &amp; FAQ · AffilStack</title>', $this->headOf($response));
        $this->assertStringNotContainsString('name="robots"', $this->headOf($response));
    }

    public function test_the_pricing_page_publishes_software_application_schema_with_real_plan_offers(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'price_monthly_cents' => 2900, 'currency' => 'USD', 'is_active' => true]);
        Plan::factory()->create(['name' => 'Pro', 'price_monthly_cents' => 9700, 'currency' => 'USD', 'is_active' => true]);

        $response = $this->get('/pricing');

        $software = $this->nodesOfType($response, 'SoftwareApplication');
        $this->assertCount(1, $software);
        $prices = array_column($software[0]['offers'], 'price', 'name');
        $this->assertSame('29.00', $prices['Starter (monthly)']);
        $this->assertSame('97.00', $prices['Pro (monthly)']);
        $this->assertSame('USD', $software[0]['offers'][0]['priceCurrency']);
    }

    public function test_an_admin_can_switch_the_pricing_schema_to_product(): void
    {
        Plan::factory()->create(['name' => 'Pro', 'price_monthly_cents' => 9700, 'is_active' => true]);
        PageSeo::create(['route_name' => 'registration.pricing', 'schema_type' => 'Product']);

        $response = $this->get('/pricing');

        $product = $this->nodesOfType($response, 'Product');
        $this->assertCount(1, $product);
        $this->assertSame(['@id' => route('home').'#organization'], $product[0]['brand']);
        $this->assertCount(0, $this->nodesOfType($response, 'SoftwareApplication'));
    }

    public function test_a_schema_type_the_page_body_already_declares_is_never_duplicated(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $this->assertCount(1, $this->nodesOfType($response, 'AboutPage'));
    }

    public function test_faq_page_schema_without_real_questions_falls_back_to_web_page(): void
    {
        PageSeo::create(['route_name' => 'contact.show', 'schema_type' => 'FAQPage']);

        $response = $this->get('/contact');

        $response->assertOk();
        $this->assertCount(0, $this->nodesOfType($response, 'FAQPage'));
        $this->assertCount(1, $this->nodesOfType($response, 'WebPage'));
    }

    public function test_the_contact_page_defaults_to_contact_page_schema(): void
    {
        $this->assertCount(1, $this->nodesOfType($this->get('/contact'), 'ContactPage'));
    }

    public function test_schema_type_none_removes_the_page_node(): void
    {
        PageSeo::create(['route_name' => 'home', 'schema_type' => 'none']);

        $response = $this->get('/');

        $this->assertCount(0, $this->nodesOfType($response, 'WebPage'));
        $this->assertCount(1, $this->nodesOfType($response, 'Organization'));
    }

    public function test_custom_json_ld_is_published_and_can_never_break_out_of_its_script_tag(): void
    {
        PageSeo::create([
            'route_name' => 'home',
            'custom_schema' => '{"@context":"https://schema.org","@type":"Event","name":"Launch </script><script>alert(1)</script>"}',
        ]);

        $response = $this->get('/');

        $response->assertDontSee('</script><script>alert(1)', false);
        $events = $this->nodesOfType($response, 'Event');
        $this->assertCount(1, $events);
        $this->assertSame('Launch </script><script>alert(1)</script>', $events[0]['name']);
    }

    public function test_a_site_page_carries_its_extended_seo_fields(): void
    {
        SitePage::create([
            'slug' => 'shipping-policy',
            'title' => 'Shipping Policy',
            'content' => '<p>We ship worldwide.</p>',
            'is_published' => true,
            'no_follow' => true,
            'focus_keyword' => 'shipping policy',
            'secondary_keywords' => ['delivery times'],
            'tags' => ['legal', 'shipping'],
            'og_title' => 'How we ship',
            'og_description' => 'Shipping explained.',
            'twitter_title' => 'Shipping, on X',
            'schema_type' => 'WebPage',
        ]);

        $response = $this->get('/shipping-policy');

        $response->assertOk();
        $head = $this->headOf($response);
        // No custom SEO title: the sitewide title template applies.
        $this->assertStringContainsString('<title>Shipping Policy · AffilStack</title>', $head);
        $this->assertStringContainsString('name="robots" content="index, nofollow"', $head);
        $this->assertStringContainsString('property="og:type" content="article"', $head);
        $this->assertStringContainsString('property="article:tag" content="legal"', $head);
        $this->assertStringContainsString('property="article:tag" content="shipping"', $head);
        $this->assertStringContainsString('property="og:title" content="How we ship"', $head);
        $this->assertStringContainsString('name="twitter:title" content="Shipping, on X"', $head);
        $this->assertStringContainsString('name="keywords" content="shipping policy, delivery times, legal, shipping"', $head);
    }

    public function test_the_title_template_is_editable(): void
    {
        SiteSetting::set('seo_title_template', '%page% | %site% for affiliates');
        SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'Body', 'is_published' => true]);

        $this->assertStringContainsString('<title>Terms | AffilStack for affiliates</title>', $this->headOf($this->get('/terms')));
    }

    public function test_social_images_prefer_the_original_png_over_its_webp_copy_and_carry_their_size(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('seo/share.png', UploadedFile::fake()->image('share.png', 1200, 630)->getContent());
        Storage::disk('public')->put('seo/share.webp', 'webp-bytes');
        SiteSetting::set('seo_og_image_path', 'seo/share.webp');

        $head = $this->headOf($this->get('/'));

        $this->assertStringContainsString('property="og:image" content="'.Storage::disk('public')->url('seo/share.png').'"', $head);
        $this->assertStringContainsString('property="og:image:width" content="1200"', $head);
        $this->assertStringContainsString('property="og:image:height" content="630"', $head);
        $this->assertStringContainsString('property="og:image:type" content="image/png"', $head);
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $head);
        $this->assertStringContainsString('name="twitter:image" content="'.Storage::disk('public')->url('seo/share.png').'"', $head);
    }

    public function test_the_edited_organization_schema_is_valid_json_ld_on_every_public_page(): void
    {
        SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'Body', 'is_published' => true]);
        SiteSetting::set('seo_social_linkedin', 'https://linkedin.com/company/affilstack');
        app(OrganizationSchema::class)->save([
            'name' => 'AffilStack',
            'legal_name' => 'AffilStack Technologies Ltd',
            'founding_date' => '2026-01-15',
            'founders' => [['name' => 'Jane Founder', 'url' => null]],
            'telephone' => '+2348000000000',
            'street_address' => '1 Marina Road',
            'address_locality' => 'Lagos',
            'address_country' => 'NG',
            'extra_same_as' => ['https://www.crunchbase.com/organization/affilstack'],
            'website_alternate_name' => 'AffilStack.com',
        ]);

        foreach (['/', '/about', '/pricing', '/help', '/learn', '/contact', '/affiliate', '/terms'] as $path) {
            $response = $this->get($path);
            $response->assertOk();

            $organization = $this->nodesOfType($response, 'Organization');
            $this->assertCount(1, $organization, "Exactly one Organization on {$path}");
            $organization = $organization[0];
            $this->assertSame('AffilStack Technologies Ltd', $organization['legalName']);
            $this->assertSame('2026-01-15', $organization['foundingDate']);
            $this->assertSame([['@type' => 'Person', 'name' => 'Jane Founder']], $organization['founder']);
            $this->assertSame('Lagos', $organization['address']['addressLocality']);
            $this->assertSame('+2348000000000', $organization['contactPoint']['telephone']);
            $this->assertSame(['https://linkedin.com/company/affilstack', 'https://www.crunchbase.com/organization/affilstack'], $organization['sameAs']);
            $this->assertStringStartsWith(url('/favicon-512x512.png'), $organization['logo']['url']);
            $this->assertSame('AffilStack.com', $this->nodesOfType($response, 'WebSite')[0]['alternateName']);
            $this->assertArrayNotHasKey('potentialAction', $this->nodesOfType($response, 'WebSite')[0]);
        }
    }

    public function test_robots_txt_is_served_from_settings_and_always_lists_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Allow: /', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);

        SiteSetting::set('seo_robots_txt', "User-agent: *\nDisallow: /afs-admin");

        $this->get('/robots.txt')
            ->assertSee('Disallow: /afs-admin', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);

        $this->assertFileDoesNotExist(public_path('robots.txt'), 'A static public/robots.txt would shadow the dynamic one.');
    }

    public function test_the_sitemap_lists_indexable_pages_with_lastmod_and_skips_noindex_and_canonicalised_ones(): void
    {
        Plan::factory()->create(['is_active' => true]);
        PageSeo::create(['route_name' => 'tutorials.index', 'robots_index' => false]);
        SitePage::create(['slug' => 'duplicate-terms', 'title' => 'Dup', 'content' => 'x', 'is_published' => true, 'canonical_url' => 'https://affilstack.com/terms']);
        SitePage::create(['slug' => 'privacy', 'title' => 'Privacy', 'content' => 'x', 'is_published' => true]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $xml = $response->getContent();
        $this->assertStringContainsString('<loc>'.route('contact.show').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('registration.pricing').'</loc>', $xml);
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('registration.pricing'), '#').'</loc>\s*<priority>[\d.]+</priority>\s*<lastmod>#', $xml);
        $this->assertStringContainsString('<loc>'.url('/privacy').'</loc>', $xml);
        $this->assertStringNotContainsString(route('tutorials.index'), $xml);
        $this->assertStringNotContainsString('duplicate-terms', $xml);
    }
}
