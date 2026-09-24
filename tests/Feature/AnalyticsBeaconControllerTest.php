<?php

namespace Tests\Feature;

use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The first-party page-view beacon (item #4, statistics area) —
 * partials/analytics-beacon.blade.php POSTs here on every page load and
 * again on page-hide. See PageView::classifySource() for the traffic-source
 * rules and AnalyticsBeaconController for why these routes skip CSRF/auth,
 * and PageView::booted() for why the duration update is authorized by an
 * unguessable per-view token rather than by session id.
 */
class AnalyticsBeaconControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_a_direct_visit_with_no_referrer(): void
    {
        $response = $this->postJson('/internal/analytics/view', [
            'path' => '/pricing',
            'referrer' => null,
            'query' => null,
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        $this->assertDatabaseHas('page_views', [
            'path' => 'pricing',
            'source' => 'Direct',
        ]);
    }

    public function test_classifies_search_engine_referrers_as_organic_search(): void
    {
        $this->postJson('/internal/analytics/view', [
            'path' => '/about',
            'referrer' => 'https://www.google.com/search?q=affilstack',
            'query' => null,
        ])->assertOk();

        $this->assertDatabaseHas('page_views', [
            'path' => 'about',
            'source' => 'Organic Search',
        ]);
    }

    public function test_utm_medium_overrides_referrer_based_classification(): void
    {
        $this->postJson('/internal/analytics/view', [
            'path' => '/pricing',
            'referrer' => 'https://www.google.com/',
            'query' => '?utm_source=newsletter&utm_medium=email',
        ])->assertOk();

        $this->assertDatabaseHas('page_views', [
            'path' => 'pricing',
            'source' => 'Email',
        ]);
    }

    public function test_skips_recording_for_bot_user_agents(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])
            ->postJson('/internal/analytics/view', ['path' => '/help'])
            ->assertOk()
            ->assertJson(['token' => null]);

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_duration_beacon_fills_in_time_on_page_using_the_returned_token(): void
    {
        $viewResponse = $this->postJson('/internal/analytics/view', ['path' => '/help']);
        $token = $viewResponse->json('token');

        $this->postJson('/internal/analytics/duration', ['token' => $token, 'seconds' => 42])
            ->assertOk();

        $this->assertDatabaseHas('page_views', ['view_token' => $token, 'duration_seconds' => 42]);
    }

    public function test_duration_beacon_ignores_an_unknown_token(): void
    {
        $view = PageView::factory()->create(['duration_seconds' => null]);

        $this->postJson('/internal/analytics/duration', ['token' => str_repeat('x', 48), 'seconds' => 999])
            ->assertOk();

        $this->assertDatabaseHas('page_views', ['id' => $view->id, 'duration_seconds' => null]);
    }

    public function test_duration_beacon_cannot_overwrite_an_already_recorded_duration(): void
    {
        $viewResponse = $this->postJson('/internal/analytics/view', ['path' => '/help']);
        $token = $viewResponse->json('token');

        $this->postJson('/internal/analytics/duration', ['token' => $token, 'seconds' => 42])->assertOk();
        $this->postJson('/internal/analytics/duration', ['token' => $token, 'seconds' => 999])->assertOk();

        $this->assertDatabaseHas('page_views', ['view_token' => $token, 'duration_seconds' => 42]);
    }
}
