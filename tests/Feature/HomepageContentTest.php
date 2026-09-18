<?php

namespace Tests\Feature;

use App\Models\HomepageFeature;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage used to go visually "thin" whenever an admin hadn't yet
 * configured any Homepage Features (Content → Homepage Features) — the
 * entire feature-grid section just disappeared. It now falls back to a
 * real description of the product instead, and also teases the actual
 * plan pricing (sourced from Plan, never hardcoded, so it can't drift
 * from the real /pricing page).
 */
class HomepageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_default_features_when_none_are_configured(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Offer research');
        $response->assertSee('AI content engine');
    }

    public function test_homepage_shows_admin_configured_features_instead_of_the_default_ones(): void
    {
        HomepageFeature::create([
            'title' => 'Custom Feature',
            'description' => 'A real admin-authored feature.',
            'media_type' => 'none',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Custom Feature');
        $response->assertDontSee('Offer research');
    }

    public function test_homepage_shows_a_pricing_teaser_sourced_from_real_plans(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'price_monthly_cents' => 1900, 'is_active' => true, 'is_featured' => false, 'sort_order' => 1]);
        Plan::factory()->create(['name' => 'Growth', 'price_monthly_cents' => 5900, 'is_active' => true, 'is_featured' => true, 'sort_order' => 2]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Starter');
        $response->assertSee('$19');
        $response->assertSee('Growth');
        $response->assertSee('$59');
        $response->assertSee('Most popular');
        $response->assertSee(route('registration.pricing'), false);
    }

    public function test_homepage_hides_the_pricing_teaser_when_there_are_no_active_plans(): void
    {
        Plan::factory()->create(['is_active' => false]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Simple, transparent pricing');
    }
}
