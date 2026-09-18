<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Suggest other things/designs that will make the homepage richer in SEO
 * and fully optimized to convert visitors into buyers." Covers the
 * concrete pieces actually built: sitemap coverage for the new pages,
 * Twitter Card meta tags, and a real (never fabricated) AggregateRating
 * that only appears once the visible Testimonials section itself does.
 */
class SeoEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_sitemap_includes_the_affiliate_landing_and_tutorials_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(route('affiliate.landing'), false);
        $response->assertSee(route('tutorials.index'), false);
        $response->assertSee(route('about'), false);
    }

    public function test_marketing_pages_carry_twitter_card_meta_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('name="twitter:card"', false);
        $response->assertSee('name="twitter:title"', false);
    }

    public function test_homepage_has_no_aggregate_rating_without_enough_rated_testimonials(): void
    {
        Testimonial::factory()->count(2)->create(['is_published' => true, 'rating' => 5]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('AggregateRating', false);
    }

    public function test_homepage_includes_a_real_aggregate_rating_once_testimonials_are_visible(): void
    {
        Testimonial::factory()->create(['is_published' => true, 'rating' => 5]);
        Testimonial::factory()->create(['is_published' => true, 'rating' => 4]);
        Testimonial::factory()->create(['is_published' => true, 'rating' => 5]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('AggregateRating', false);
        // (5 + 4 + 5) / 3 = 4.7
        $response->assertSee('4.7', false);
    }

    public function test_the_header_has_a_prominent_get_started_button(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Get started');
    }
}
