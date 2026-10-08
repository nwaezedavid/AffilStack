<?php

namespace Tests\Feature;

use App\Filament\Resources\FeaturePages\Pages\EditFeaturePage;
use App\Models\FeaturePage;
use App\Models\User;
use App\Support\Features\FeatureCatalog;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Public feature pages (/features, /features/{slug}): seeded for every tool
 * with real screenshots shipped in public/images/features, a video slot per
 * section, and editable in Website → Features.
 */
class FeaturePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_catalog_tool_is_seeded_and_ships_its_screenshots(): void
    {
        $this->assertSame(count(FeatureCatalog::all()), FeaturePage::count());

        foreach (FeatureCatalog::all() as $page) {
            $images = array_merge([$page['hero_default_image']], array_column($page['sections'], 'default_image'));

            foreach ($images as $image) {
                $this->assertFileExists(public_path('images/features/'.$image), "{$page['slug']} needs {$image}");
            }
        }
    }

    public function test_every_feature_page_renders_with_its_default_screenshot_and_no_empty_video_slot(): void
    {
        foreach (FeaturePage::all() as $page) {
            $response = $this->get('/features/'.$page->slug);

            $response->assertOk()
                ->assertSee(e($page->name), false)
                ->assertSee(asset('images/features/'.$page->hero_default_image), false)
                ->assertSee('data-feature-section', false)
                ->assertDontSee('data-section-video', false)
                ->assertSee('"@type":"BreadcrumbList"', false);
        }
    }

    public function test_a_youtube_link_on_a_section_shows_the_video(): void
    {
        $page = FeaturePage::where('slug', 'offer-research')->first();
        $sections = $page->sections;
        $sections[0]['video_url'] = 'https://youtu.be/dQw4w9WgXcQ';
        $sections[1]['video_url'] = 'https://vimeo.com/123';
        $page->update(['sections' => $sections]);

        $html = $this->get('/features/offer-research')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-section-video'));
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
    }

    public function test_feature_faqs_publish_faq_page_schema(): void
    {
        $this->get('/features/chrome-extension')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('Does the extension message people automatically?');
    }

    public function test_an_unpublished_feature_page_is_not_served_or_listed(): void
    {
        FeaturePage::where('slug', 'swipe-files')->update(['is_published' => false]);
        FeaturePage::flushCache();

        $this->get('/features/swipe-files')->assertNotFound();
        $this->get('/features')->assertOk()->assertDontSee('data-feature-card="swipe-files"', false)->assertSee('data-feature-card="offer-research"', false);
    }

    public function test_the_overview_lists_every_tool_grouped_by_menu_column(): void
    {
        $response = $this->get('/features')->assertOk();

        foreach (FeaturePage::all() as $page) {
            $response->assertSee('data-feature-card="'.$page->slug.'"', false);
        }

        $response->assertSee('id="group-research"', false)->assertSee('id="group-grow"', false)->assertSee('"@type":"ItemList"', false);
    }

    public function test_the_sitemap_lists_the_features_faq_and_every_feature_page(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee('<loc>'.route('features.index').'</loc>', false)
            ->assertSee('<loc>'.route('faq.index').'</loc>', false);

        foreach (FeaturePage::all() as $page) {
            $response->assertSee('<loc>'.route('features.show', $page->slug).'</loc>', false);
        }
    }

    public function test_admin_edits_and_an_uploaded_screenshot_override_show_on_the_public_page(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $page = FeaturePage::where('slug', 'earnings-tracker')->first();

        Livewire::actingAs($admin)
            ->test(EditFeaturePage::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'hero_headline' => 'Know your *real* commission',
                'hero_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh()->update(['hero_image_path' => 'features/custom-earnings.webp']);

        $this->get('/features/earnings-tracker')
            ->assertOk()
            ->assertSee('Know your <span', false)
            ->assertSee('/storage/features/custom-earnings.webp', false)
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_admin_rejects_a_script_link_in_the_closing_button(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(EditFeaturePage::class, ['record' => 'link-tracking'])
            ->fillForm(['cta_url' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasFormErrors(['cta_url']);
    }
}
