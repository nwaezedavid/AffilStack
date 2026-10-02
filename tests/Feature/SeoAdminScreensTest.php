<?php

namespace Tests\Feature;

use App\Filament\Pages\OrganizationSchemaSettings;
use App\Filament\Pages\SeoSettings;
use App\Filament\Resources\PageSeos\Pages\EditPageSeo;
use App\Filament\Resources\PageSeos\Pages\ListPageSeos;
use App\Filament\Resources\PageSeos\PageSeoResource;
use App\Filament\Resources\SitePages\Pages\EditSitePage;
use App\Models\PageSeo;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Seo\OrganizationSchema;
use App\Services\Seo\SeoMetaAssistant;
use App\Support\Seo\PublicPages;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The SEO admin screens: SEO > Page SEO (list + editor), the extended Site
 * Page SEO tab, SEO settings (global defaults) and the Organization schema
 * form.
 */
class SeoAdminScreensTest extends TestCase
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

    public function test_the_page_seo_list_shows_every_public_page_with_a_score(): void
    {
        SitePage::create(['slug' => 'terms', 'title' => 'Terms of Service', 'content' => 'Body', 'is_published' => true]);

        $component = Livewire::actingAs($this->admin)->test(ListPageSeos::class)->assertOk();

        foreach (PublicPages::all() as $page) {
            $component->assertSee($page['label']);
        }

        $component->assertSee('Terms of Service')->assertSee('/ 100');
        $this->assertSame(count(PublicPages::all()), PageSeo::count());
    }

    public function test_an_admin_can_edit_a_route_pages_seo(): void
    {
        PageSeo::syncRegistry();
        $home = PageSeo::where('route_name', 'home')->first();

        Livewire::actingAs($this->admin)
            ->test(EditPageSeo::class, ['record' => $home->getRouteKey()])
            ->assertSee('SEO: Homepage')
            ->assertSee('Search appearance')
            ->fillForm([
                'focus_keyword' => 'affiliate marketing platform',
                'secondary_keywords' => ['affiliate software', 'AI content'],
                'tags' => ['brand'],
                'seo_title' => 'AffilStack — the affiliate marketing platform',
                'meta_description' => 'Research offers, write content with AI and track every sale in one affiliate marketing platform built for solo marketers and small teams.',
                'og_title' => 'Meet AffilStack',
                'robots_follow' => false,
                'schema_type' => 'WebPage',
                'custom_schema' => '{"@context":"https://schema.org","@type":"Event","name":"Launch"}',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $home->refresh();
        $this->assertSame(['affiliate software', 'AI content'], $home->secondary_keywords);
        $this->assertSame(['brand'], $home->tags);
        $this->assertSame('Meet AffilStack', $home->og_title);
        $this->assertFalse($home->robots_follow);
        $this->assertTrue($home->robots_index);

        $this->get('/')->assertSee('<title>AffilStack — the affiliate marketing platform</title>', false);
    }

    public function test_invalid_custom_json_ld_is_rejected(): void
    {
        PageSeo::syncRegistry();
        $home = PageSeo::where('route_name', 'home')->first();

        Livewire::actingAs($this->admin)
            ->test(EditPageSeo::class, ['record' => $home->getRouteKey()])
            ->fillForm(['custom_schema' => '{"@type": "Event",}'])
            ->call('save')
            ->assertHasFormErrors(['custom_schema']);

        Livewire::actingAs($this->admin)
            ->test(EditPageSeo::class, ['record' => $home->getRouteKey()])
            ->fillForm(['custom_schema' => '"just a string"'])
            ->call('save')
            ->assertHasFormErrors(['custom_schema']);
    }

    public function test_the_checklist_covers_secondary_keywords_and_the_social_image(): void
    {
        PageSeo::syncRegistry();
        $pricing = PageSeo::where('route_name', 'registration.pricing')->first();

        Livewire::actingAs($this->admin)
            ->test(EditPageSeo::class, ['record' => $pricing->getRouteKey()])
            ->fillForm([
                'focus_keyword' => 'pricing',
                'seo_title' => 'Pricing · %site%',
                'secondary_keywords' => ['monthly plans', 'nowhere-to-be-found'],
            ])
            ->assertSee('SEO score')
            ->assertSee('2 secondary keyword(s) set')
            ->assertSee('of 2 secondary keywords appear on the page')
            ->assertSee('No social share image')
            ->assertSee('Focus keyword appears in the SEO title')
            ->assertSee('Pricing · AffilStack');
    }

    public function test_generate_with_ai_fills_the_title_and_description_using_the_keywords(): void
    {
        PageSeo::syncRegistry();
        $home = PageSeo::where('route_name', 'home')->first();

        $this->mock(SeoMetaAssistant::class, function (MockInterface $mock) {
            $mock->shouldReceive('suggest')
                ->once()
                ->withArgs(fn ($title, $content, $keyword, $secondary) => $keyword === 'affiliate platform' && $secondary === ['ai content'])
                ->andReturn(['seo_title' => 'AI title', 'meta_description' => 'AI description']);
        });

        Livewire::actingAs($this->admin)
            ->test(EditPageSeo::class, ['record' => $home->getRouteKey()])
            ->fillForm(['focus_keyword' => 'affiliate platform', 'secondary_keywords' => ['ai content']])
            ->callAction(TestAction::make('generate_seo_meta')->schemaComponent('seo_title'))
            ->assertSchemaStateSet(['seo_title' => 'AI title', 'meta_description' => 'AI description']);
    }

    public function test_page_seo_cannot_be_created_by_hand(): void
    {
        $this->assertFalse(PageSeoResource::canCreate());
    }

    public function test_page_seo_is_scoped_to_the_site_department(): void
    {
        $content = User::factory()->create();
        $content->assignRole('admin_sub');
        $content->syncPermissions(['department.content']);

        $this->actingAs($content);
        $this->assertFalse(PageSeoResource::canAccess());

        $site = User::factory()->create();
        $site->assignRole('admin_sub');
        $site->syncPermissions(['department.site']);

        $this->actingAs($site);
        $this->assertTrue(PageSeoResource::canAccess());
    }

    public function test_an_admin_can_set_the_extended_seo_fields_on_a_site_page(): void
    {
        $page = SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'Body', 'is_published' => true]);

        Livewire::actingAs($this->admin)
            ->test(EditSitePage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'secondary_keywords' => ['terms of use'],
                'tags' => ['legal'],
                'canonical_url' => 'https://affilstack.com/terms',
                'no_follow' => true,
                'twitter_title' => 'Our terms',
                'schema_type' => 'WebPage',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame(['terms of use'], $page->secondary_keywords);
        $this->assertSame(['legal'], $page->tags);
        $this->assertSame('https://affilstack.com/terms', $page->canonical_url);
        $this->assertTrue($page->no_follow);
        $this->assertSame('Our terms', $page->twitter_title);
    }

    public function test_seo_settings_save_the_global_defaults(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SeoSettings::class)
            ->fillForm([
                'seo_title_template' => '%page% — %site%',
                'seo_twitter_handle' => '@affilstack',
                'seo_facebook_app_id' => '1234567890',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('%page% — %site%', SiteSetting::get('seo_title_template'));
        $this->assertSame('affilstack', SiteSetting::get('seo_twitter_handle'));
        $this->assertSame('1234567890', SiteSetting::get('seo_facebook_app_id'));
    }

    public function test_robots_rules_that_would_hide_the_favicon_or_assets_are_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SeoSettings::class)
            ->fillForm(['seo_robots_txt' => "User-agent: *\nDisallow: /storage/\nDisallow: /favicon.ico"])
            ->call('save')
            ->assertHasFormErrors(['seo_robots_txt']);

        Livewire::actingAs($this->admin)
            ->test(SeoSettings::class)
            ->fillForm(['seo_robots_txt' => "User-agent: *\nDisallow: /afs-admin/\n\nUser-agent: Googlebot-Image\nDisallow: /storage/"])
            ->call('save')
            ->assertHasFormErrors(['seo_robots_txt']);

        Livewire::actingAs($this->admin)
            ->test(SeoSettings::class)
            ->fillForm(['seo_robots_txt' => "User-agent: *\nDisallow: /afs-admin/"])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_the_organization_schema_form_is_prefilled_and_saves(): void
    {
        SiteSetting::set('support_email', 'hello@affilstack.com');
        SiteSetting::set('about_founder_name', 'Jane Founder');

        Livewire::actingAs($this->admin)
            ->test(OrganizationSchemaSettings::class)
            ->assertSchemaStateSet([
                'name' => 'AffilStack',
                'email' => 'hello@affilstack.com',
                'website_name' => 'AffilStack',
            ])
            ->fillForm([
                'legal_name' => 'AffilStack Technologies Ltd',
                'address_locality' => 'Lagos',
                'address_country' => 'NG',
                'extra_same_as' => ['https://www.producthunt.com/products/affilstack'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(OrganizationSchema::class)->settings();
        $this->assertSame('AffilStack Technologies Ltd', $settings['legal_name']);
        $this->assertSame([['name' => 'Jane Founder', 'url' => null]], $settings['founders']);

        $this->get('/')
            ->assertSee('"legalName":"AffilStack Technologies Ltd"', false)
            ->assertSee('https://www.producthunt.com/products/affilstack', false);
    }
}
