<?php

namespace Tests\Feature;

use App\Filament\Pages\FeatureDemoSettings;
use App\Models\FeaturePage;
use App\Models\User;
use App\Support\Features\FeatureMedia;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "An animated version of each feature screenshot … The screenshots will
 * remain but I can switch/select the one I want from the admin dashboard."
 */
class FeatureDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('public');
    }

    public function test_screenshots_stay_the_default_even_when_animations_are_installed(): void
    {
        Storage::disk('public')->put('feature-animations/offer-research.mp4', 'video');

        $this->assertSame([], FeatureMedia::videoFor(asset('images/features/offer-research.webp')));
        $this->assertSame(1, FeatureMedia::installedCount());
    }

    public function test_choosing_the_animation_swaps_the_screenshot_for_the_video(): void
    {
        Storage::disk('public')->put('feature-animations/offer-research.mp4', 'video');
        FeatureMedia::save(['offer-research' => FeatureMedia::ANIMATION], []);

        [$video] = FeatureMedia::videoFor(asset('images/features/offer-research.webp'));

        $this->assertStringEndsWith('feature-animations/offer-research.mp4', $video['url']);
        $this->assertSame('video/mp4', $video['type']);
        // Only the screenshot it belongs to.
        $this->assertSame([], FeatureMedia::videoFor(asset('images/features/crm-list.webp')));
    }

    public function test_the_mp4_is_offered_first_with_a_webm_fallback(): void
    {
        Storage::disk('public')->put('feature-animations/offer-research.mp4', 'video');
        Storage::disk('public')->put('feature-animations/offer-research.webm', 'video');
        FeatureMedia::save(['offer-research' => FeatureMedia::ANIMATION], []);

        $sources = FeatureMedia::videoFor(asset('images/features/offer-research.webp'));

        $this->assertSame(['video/mp4', 'video/webm'], array_column($sources, 'type'));
        $this->assertSame('feature-animations/offer-research.mp4', FeatureMedia::bundledVideoPath('offer-research'));
    }

    public function test_an_animation_without_a_video_keeps_the_screenshot(): void
    {
        FeatureMedia::save(['offer-research' => FeatureMedia::ANIMATION], []);

        $this->assertSame([], FeatureMedia::videoFor(asset('images/features/offer-research.webp')));
    }

    public function test_an_uploaded_video_wins_over_the_bundled_one(): void
    {
        Storage::disk('public')->put('feature-animations/offer-research.mp4', 'video');
        Storage::disk('public')->put('feature-animations/uploads/mine.webm', 'video');
        FeatureMedia::save(['offer-research' => FeatureMedia::ANIMATION], ['offer-research' => 'feature-animations/uploads/mine.webm']);

        $sources = FeatureMedia::videoFor(asset('images/features/offer-research.webp'));
        $this->assertCount(1, $sources);
        $video = $sources[0];

        $this->assertStringEndsWith('feature-animations/uploads/mine.webm', $video['url']);
        $this->assertSame('video/webm', $video['type']);
    }

    public function test_only_videos_in_the_upload_folder_are_accepted(): void
    {
        Storage::disk('public')->put('feature-animations/offer-research.mp4', 'video');
        FeatureMedia::save(['offer-research' => FeatureMedia::ANIMATION], ['offer-research' => '../../.env']);

        $this->assertSame([], FeatureMedia::uploadedVideos());
        $this->assertStringEndsWith('feature-animations/offer-research.mp4', FeatureMedia::videoFor(asset('images/features/offer-research.webp'))[0]['url']);
    }

    public function test_images_the_admin_uploaded_are_never_swapped(): void
    {
        Storage::disk('public')->put('feature-animations/offer-research.mp4', 'video');
        FeatureMedia::save(['offer-research' => FeatureMedia::ANIMATION], []);

        $this->assertSame([], FeatureMedia::videoFor(Storage::disk('public')->url('features/offer-research.webp')));
        $this->assertSame([], FeatureMedia::videoFor('https://evil.example/images/features/../offer-research.webp'));
    }

    public function test_the_feature_page_and_cards_play_the_animation(): void
    {
        $page = FeaturePage::query()->where('is_published', true)->whereNull('hero_image_path')->firstOrFail();
        $name = FeatureMedia::nameFor($page->heroImageUrl());
        $this->assertNotNull($name);

        $this->get($page->url())->assertOk()->assertDontSee('data-feature-animation', false);

        Storage::disk('public')->put("feature-animations/{$name}.mp4", 'video');
        FeatureMedia::save([$name => FeatureMedia::ANIMATION], []);

        $this->get($page->url())->assertOk()
            ->assertSee('data-feature-animation', false)
            ->assertSee("feature-animations/{$name}.mp4", false)
            ->assertSee('poster="'.asset("images/features/{$name}.webp"), false)
            ->assertSee('prefers-reduced-motion', false);

        $this->get(route('features.index'))->assertOk()->assertSee("feature-animations/{$name}.mp4", false);
    }

    public function test_the_admin_page_switches_each_screenshot_and_everything_at_once(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(FeatureDemoSettings::class)
            ->assertSee('Offer research')
            ->assertSee('0 of 31 animated demos are installed.')
            ->fillForm(['choice_offer_research' => FeatureMedia::ANIMATION])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame(FeatureMedia::ANIMATION, FeatureMedia::choiceFor('offer-research'));
        $this->assertSame(FeatureMedia::SCREENSHOT, FeatureMedia::choiceFor('crm-list'));

        Livewire::actingAs($admin)
            ->test(FeatureDemoSettings::class)
            ->callAction('allAnimations');
        $this->assertSame(FeatureMedia::ANIMATION, FeatureMedia::choiceFor('crm-list'));

        Livewire::actingAs($admin)
            ->test(FeatureDemoSettings::class)
            ->callAction('allScreenshots')
            ->assertFormSet(['choice_offer_research' => FeatureMedia::SCREENSHOT]);
        $this->assertSame(FeatureMedia::SCREENSHOT, FeatureMedia::choiceFor('offer-research'));
    }

    public function test_the_admin_can_upload_their_own_video(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(FeatureDemoSettings::class)
            ->fillForm([
                'choice_crm_list' => FeatureMedia::ANIMATION,
                'video_crm_list' => UploadedFile::fake()->create('demo.mp4', 500, 'video/mp4'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = FeatureMedia::uploadedVideos()['crm-list'];
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith($path, FeatureMedia::videoFor(asset('images/features/crm-list.webp'))[0]['url']);
    }

    public function test_a_non_video_upload_is_refused(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(FeatureDemoSettings::class)
            ->fillForm(['video_crm_list' => UploadedFile::fake()->create('evil.html', 5, 'text/html')])
            ->call('save')
            ->assertHasFormErrors(['video_crm_list']);
    }
}
