<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Filament\Pages\HomepageEditor;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Homepage\HomepageContent;
use App\Support\OutboundUrlGuard;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "In the admin dashboard area, add more features to enable me edit the
 * homepage whenever I want (Make it easy to do)." — Content → Homepage.
 */
class HomepageEditorPageTest extends TestCase
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

    public function test_the_editor_opens_with_the_current_sections_steps_and_stats(): void
    {
        $component = Livewire::actingAs($this->admin)->test(HomepageEditor::class)->assertOk();

        $data = $component->get('data');
        $this->assertSame(array_keys(HomepageContent::SECTIONS), array_column(array_values($data['home_sections']), 'key'));
        $this->assertCount(4, $data['home_steps']);
        $this->assertCount(4, $data['home_stats']);
    }

    public function test_saving_stores_copy_order_and_toggles_and_the_homepage_reflects_it_immediately(): void
    {
        $this->get('/')->assertOk()->assertSee('Find the offer.');

        $sections = collect(HomepageContent::sections())
            ->map(fn (array $s) => ['key' => $s['key'], 'enabled' => $s['key'] !== 'stats'])
            ->reverse()
            ->values()
            ->all();

        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm([
                'hero_headline' => 'Your affiliate business, *on autopilot*',
                'home_hero_eyebrow' => 'Built in Lagos',
                'home_video_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'home_cta_button_url' => '/pricing',
                'home_sections' => $sections,
                'home_steps' => [
                    ['icon' => 'map-pin', 'title' => 'Find buyers', 'description' => 'On Google Maps.'],
                    ['icon' => 'banknotes', 'title' => 'Get paid', 'description' => 'Track commission.'],
                ],
                'blog_feed_enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Homepage saved');

        $this->assertSame('Your affiliate business, *on autopilot*', SiteSetting::get('hero_headline'));
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', SiteSetting::get('home_video_url'));
        $this->assertFalse(SiteSetting::flag('blog_feed_enabled'));
        $this->assertSame('cta', json_decode(SiteSetting::get('home_sections'), true)[0]['key']);
        $this->assertSame([['icon' => 'map-pin', 'title' => 'Find buyers', 'description' => 'On Google Maps.'], ['icon' => 'banknotes', 'title' => 'Get paid', 'description' => 'Track commission.']], json_decode(SiteSetting::get('home_steps'), true));

        $html = $this->get('/')->assertOk()
            ->assertSee('on autopilot</span>', false)
            ->assertSee('Built in Lagos')
            ->assertSee('id="see-it-in-action"', false)
            ->assertSee('Find buyers')
            ->assertDontSee('Built to replace your whole affiliate stack')
            ->getContent();

        // Reversed order: the closing call to action now comes before the hero.
        $this->assertLessThan(strpos($html, 'id="hero-heading"'), strpos($html, 'id="cta-heading"'));
    }

    public function test_an_unrecognisable_youtube_link_is_rejected_with_a_helpful_message(): void
    {
        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm(['home_video_url' => 'https://vimeo.com/123'])
            ->call('save')
            ->assertHasFormErrors(['home_video_url']);

        $this->assertNull(SiteSetting::get('home_video_url'));
    }

    public function test_script_links_are_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm(['home_hero_primary_url' => 'javascript:alert(1)', 'header_announcement_link_url' => 'data:text/html,hi'])
            ->call('save')
            ->assertHasFormErrors(['home_hero_primary_url', 'header_announcement_link_url']);
    }

    public function test_in_page_anchor_links_are_allowed(): void
    {
        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm(['home_hero_secondary_url' => '#see-it-in-action'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/')->assertSee('href="#see-it-in-action"', false);
    }

    public function test_a_private_blog_address_is_rejected(): void
    {
        OutboundUrlGuard::resolveUsing(fn (string $host): array => ['10.0.0.5']);

        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm(['blog_feed_url' => 'https://intranet.local'])
            ->call('save')
            ->assertHasFormErrors(['blog_feed_url']);
    }

    public function test_the_hero_image_is_uploaded_as_webp_with_its_alt_text(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->fillForm([
                'hero_media_type' => 'image',
                'hero_image_path' => UploadedFile::fake()->image('dashboard.png', 1600, 1000),
                'hero_image_alt' => 'AffilStack research dashboard',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = SiteSetting::get('hero_image_path');
        $this->assertStringEndsWith('.webp', $path);

        $this->get('/')->assertSee('alt="AffilStack research dashboard"', false)->assertSee($path, false);
    }

    public function test_the_header_preview_action_opens_the_homepage(): void
    {
        $this->freezeTime();

        Livewire::actingAs($this->admin)
            ->test(HomepageEditor::class)
            ->assertActionExists('preview')
            ->assertActionHasUrl('preview', route('home').'?preview='.now()->timestamp);
    }

    public function test_a_content_sub_account_can_edit_the_homepage_but_others_cannot(): void
    {
        $content = User::factory()->create();
        $content->assignRole('admin_sub');
        $content->syncPermissions(['department.content']);

        $billing = User::factory()->create();
        $billing->assignRole('admin_sub');
        $billing->syncPermissions(['department.billing']);

        $this->actingAs($content);
        $this->assertTrue(HomepageEditor::canAccess());

        $this->actingAs($billing);
        $this->assertFalse(HomepageEditor::canAccess());
    }

    public function test_brand_settings_no_longer_overwrites_the_hero(): void
    {
        SiteSetting::set('hero_headline', 'Set in the homepage editor');
        SiteSetting::set('hero_media_type', 'youtube');

        Livewire::actingAs($this->admin)
            ->test(BrandSettings::class)
            ->fillForm(['site_name' => 'AffilStack'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Set in the homepage editor', SiteSetting::get('hero_headline'));
        $this->assertSame('youtube', SiteSetting::get('hero_media_type'));
    }
}
