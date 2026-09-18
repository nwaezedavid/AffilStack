<?php

namespace Tests\Feature;

use App\Filament\Resources\Tutorials\TutorialResource;
use App\Models\Tutorial;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "A dedicated page for tutorials on how the platform works ... visible to
 * the public as a learning centre." Admin creates a topic/title + YouTube
 * link (Filament: Content > Tutorials); the public /learn page embeds them,
 * grouped by category. See Tutorial::publishedGrouped().
 */
class TutorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_the_public_learning_centre_page_loads(): void
    {
        $this->get(route('tutorials.index'))->assertSuccessful()->assertSee('Learning Centre');
    }

    public function test_it_shows_published_tutorials_grouped_by_category_and_hides_unpublished(): void
    {
        Tutorial::factory()->create(['title' => 'Researching your first offer', 'category' => 'Getting started', 'is_published' => true]);
        Tutorial::factory()->create(['title' => 'Setting up payouts', 'category' => 'Payments', 'is_published' => true]);
        Tutorial::factory()->create(['title' => 'Unpublished draft', 'category' => 'Getting started', 'is_published' => false]);

        $response = $this->get(route('tutorials.index'));

        $response->assertOk();
        $response->assertSee('Getting started');
        $response->assertSee('Payments');
        $response->assertSee('Researching your first offer');
        $response->assertSee('Setting up payouts');
        $response->assertDontSee('Unpublished draft');
    }

    public function test_a_tutorial_with_no_category_falls_back_to_general(): void
    {
        Tutorial::factory()->create(['title' => 'A general tip', 'category' => null, 'is_published' => true]);

        $response = $this->get(route('tutorials.index'));

        $response->assertOk();
        $response->assertSee('General');
        $response->assertSee('A general tip');
    }

    public function test_the_youtube_url_embeds_correctly(): void
    {
        $tutorial = Tutorial::factory()->create(['youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'is_published' => true]);

        $response = $this->get(route('tutorials.index'));

        $response->assertOk();
        $response->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_a_slug_is_generated_automatically_from_the_title_when_left_blank(): void
    {
        $tutorial = Tutorial::create([
            'title' => 'How To Track Your Referrals',
            'youtube_url' => 'https://www.youtube.com/watch?v=abc12345678',
            'is_published' => true,
        ]);

        $this->assertSame('how-to-track-your-referrals', $tutorial->slug);
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        $first = Tutorial::create(['title' => 'Getting Started', 'youtube_url' => 'https://www.youtube.com/watch?v=abc12345678', 'is_published' => true]);
        $second = Tutorial::create(['title' => 'Getting Started', 'youtube_url' => 'https://www.youtube.com/watch?v=xyz98765432', 'is_published' => true]);

        $this->assertSame('getting-started', $first->slug);
        $this->assertSame('getting-started-2', $second->slug);
    }

    public function test_the_published_grouped_list_is_cached_and_busted_on_save(): void
    {
        $tutorial = Tutorial::factory()->create(['is_published' => true]);

        Tutorial::publishedGrouped();

        DB::enableQueryLog();
        Tutorial::publishedGrouped();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        $tutorial->update(['is_published' => false]);

        $this->assertCount(0, Tutorial::publishedGrouped()->flatten());
    }

    public function test_a_department_scoped_sub_account_without_content_access_cannot_reach_the_resource(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(TutorialResource::canAccess());

        $subAccount->syncPermissions(['department.content']);
        $this->assertTrue(TutorialResource::canAccess());
    }
}
