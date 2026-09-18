<?php

namespace Tests\Feature;

use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "The testimonial section will appear as soon as I have a minimum of 3
 * updated in the admin dashboard area." See Testimonial::published() for
 * the exact threshold logic this test file is built around.
 */
class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_homepage_hides_the_section_with_zero_testimonials(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
    }

    public function test_homepage_hides_the_section_with_only_two_published_testimonials(): void
    {
        Testimonial::factory()->count(2)->create(['is_published' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
    }

    public function test_homepage_shows_the_section_once_three_are_published(): void
    {
        Testimonial::factory()->create(['author_name' => 'Jamie Rivera', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Priya Shah', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Lee Brooks', 'is_published' => true]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('What affiliates are saying');
        $response->assertSee('Jamie Rivera');
        $response->assertSee('Priya Shah');
        $response->assertSee('Lee Brooks');
    }

    public function test_unpublished_testimonials_never_count_toward_the_minimum_or_display(): void
    {
        Testimonial::factory()->create(['author_name' => 'Published One', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Published Two', 'is_published' => true]);
        Testimonial::factory()->create(['author_name' => 'Draft Person', 'is_published' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('What affiliates are saying');
        $response->assertDontSee('Draft Person');
    }

    public function test_the_published_list_is_cached_and_busted_on_save(): void
    {
        Testimonial::factory()->count(3)->create(['is_published' => true]);

        $first = Testimonial::published();
        $this->assertCount(3, $first);

        DB::enableQueryLog();
        Testimonial::published();
        $this->assertEmpty(DB::getQueryLog(), 'A cached read should not hit the database again.');
        DB::disableQueryLog();

        // Unpublishing one drops the count below the minimum — the whole
        // section should disappear, not just show 2.
        Testimonial::first()->update(['is_published' => false]);
        $this->assertCount(0, Testimonial::published());
    }

    public function test_a_department_scoped_sub_account_without_content_access_cannot_reach_the_resource(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(TestimonialResource::canAccess());

        $subAccount->syncPermissions(['department.content']);
        $this->assertTrue(TestimonialResource::canAccess());
    }
}
