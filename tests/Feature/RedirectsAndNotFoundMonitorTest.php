<?php

namespace Tests\Feature;

use App\Filament\Resources\NotFoundLogs\Pages\ListNotFoundLogs;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SitePage;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * RankMath's "Redirections" + "404 Monitor" — a dead/changed URL sends
 * visitors somewhere real instead of a bare 404 (Redirect,
 * RedirectFallbackController), and every genuine 404 is logged (deduped,
 * capped) so an admin can turn a real pattern into a redirect from the
 * dashboard (NotFoundLog).
 */
class RedirectsAndNotFoundMonitorTest extends TestCase
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

    public function test_an_unmatched_path_is_logged_and_returns_a_real_404(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();

        $log = NotFoundLog::where('path', 'this-page-does-not-exist')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->hits_count);
    }

    public function test_repeat_hits_to_the_same_path_increment_the_existing_row_instead_of_duplicating(): void
    {
        $this->get('/dead-link');
        $this->get('/dead-link');
        $this->get('/dead-link');

        $this->assertSame(1, NotFoundLog::where('path', 'dead-link')->count());
        $this->assertSame(3, NotFoundLog::where('path', 'dead-link')->value('hits_count'));
    }

    public function test_the_404_log_stops_growing_once_it_hits_its_cap_but_still_increments_existing_paths(): void
    {
        $now = now();
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = ['path' => "bot-scan-{$i}", 'hits_count' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now];
        }
        NotFoundLog::insert($rows);

        $this->get('/bot-scan-0');
        $this->get('/a-brand-new-path-past-the-cap');

        $this->assertSame(2, NotFoundLog::where('path', 'bot-scan-0')->value('hits_count'));
        $this->assertSame(0, NotFoundLog::where('path', 'a-brand-new-path-past-the-cap')->count());
        $this->assertSame(500, NotFoundLog::count());
    }

    public function test_a_configured_redirect_sends_visitors_to_the_new_location_and_records_a_hit(): void
    {
        $redirect = Redirect::create(['from_path' => 'old-page', 'to_path' => '/pricing', 'status_code' => 301]);

        $response = $this->get('/old-page');

        $response->assertRedirect('/pricing');
        $response->assertStatus(301);
        $this->assertSame(1, $redirect->fresh()->hits_count);
        $this->assertNotNull($redirect->fresh()->last_hit_at);

        // A matched redirect is never treated as a 404.
        $this->assertSame(0, NotFoundLog::where('path', 'old-page')->count());
    }

    public function test_a_redirect_hit_is_never_logged_as_a_404(): void
    {
        Redirect::create(['from_path' => 'moved', 'to_path' => '/help', 'status_code' => 302]);

        $this->get('/moved')->assertRedirect('/help')->assertStatus(302);

        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_the_admin_panel_path_is_never_redirect_checked_or_logged(): void
    {
        $response = $this->get('/afs-admin/some-nonexistent-page');

        $response->assertNotFound();
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_the_login_redirect_path_is_never_redirect_checked_or_logged_either(): void
    {
        // /afs-login itself is a real registered route (a redirect to the
        // login form) so this never even reaches the fallback controller,
        // but anything else under that prefix should still be excluded the
        // same way /afs-admin/* is.
        $response = $this->get('/afs-login/some-nonexistent-page');

        $response->assertNotFound();
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_api_and_webhook_prefixes_are_never_redirect_checked_or_logged(): void
    {
        $this->getJson('/api/some-nonexistent-endpoint')->assertNotFound();
        $this->get('/webhooks/some-nonexistent-provider')->assertNotFound();

        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_admin_can_create_and_edit_a_redirect_from_filament(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRedirect::class)
            ->fillForm([
                'from_path' => '/promo-2025',
                'to_path' => '/pricing',
                'status_code' => 301,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $redirect = Redirect::where('to_path', '/pricing')->first();
        $this->assertNotNull($redirect);
        // The leading slash is stripped so it matches request()->path()'s shape.
        $this->assertSame('promo-2025', $redirect->from_path);
    }

    public function test_admin_can_turn_a_logged_404_into_a_redirect_from_the_monitor(): void
    {
        $log = NotFoundLog::create([
            'path' => 'old-campaign-link',
            'hits_count' => 4,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListNotFoundLogs::class)
            ->assertSuccessful()
            ->callTableAction('createRedirect', $log, data: ['to_path' => '/pricing', 'status_code' => 301]);

        $redirect = Redirect::where('from_path', 'old-campaign-link')->first();
        $this->assertNotNull($redirect);
        $this->assertSame('/pricing', $redirect->to_path);

        // The path now resolves via the redirect instead of 404ing again.
        $this->get('/old-campaign-link')->assertRedirect('/pricing');
    }

    /**
     * Regression test for a real bug found in review: redirects.from_path/
     * not_found_logs.path/referer are plain varchar(255) columns on the
     * real (MySQL) production database — an unauthenticated visitor's
     * request path has no length limit of its own, so without truncation
     * this would 500 in production (invisible against the test suite's
     * SQLite database, which never enforces column length at all).
     */
    public function test_an_extremely_long_request_path_is_truncated_rather_than_crashing(): void
    {
        $longPath = str_repeat('a', 500);

        $response = $this->get('/'.$longPath);

        $response->assertNotFound();

        $log = NotFoundLog::first();
        $this->assertNotNull($log);
        $this->assertLessThanOrEqual(200, strlen($log->path));
    }

    public function test_an_extremely_long_referer_header_is_truncated_rather_than_crashing(): void
    {
        $response = $this->withHeaders(['referer' => 'https://example.com/'.str_repeat('b', 500)])
            ->get('/some-dead-link');

        $response->assertNotFound();

        $log = NotFoundLog::first();
        $this->assertNotNull($log);
        $this->assertLessThanOrEqual(200, strlen((string) $log->referer));
    }

    /**
     * Confirms the exception type NotFoundLog::record() catches to survive
     * a genuine race (two concurrent first-ever hits on the same brand-new
     * path both passing the "does it exist" check before either inserts)
     * actually matches what this app's database driver throws for a
     * unique-constraint violation — the real failure mode this guards
     * against, verified directly since true concurrency can't be
     * deterministically reproduced in a single-process test.
     */
    public function test_creating_two_not_found_logs_with_the_same_path_throws_the_exception_type_record_catches(): void
    {
        NotFoundLog::create(['path' => 'race-path', 'hits_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);

        NotFoundLog::create(['path' => 'race-path', 'hits_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    public function test_admin_paths_are_never_redirect_checked_or_logged(): void
    {
        $response = $this->get('/admin/some-nonexistent-page');

        $response->assertNotFound();
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_a_redirect_cannot_point_at_itself(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRedirect::class)
            ->fillForm(['from_path' => 'loop-page', 'to_path' => '/loop-page', 'status_code' => 301])
            ->call('create')
            ->assertHasFormErrors(['to_path']);

        $this->assertSame(0, Redirect::count());
    }

    public function test_a_redirect_cannot_complete_a_multi_hop_cycle(): void
    {
        Redirect::create(['from_path' => 'page-b', 'to_path' => '/page-a', 'status_code' => 301]);

        // page-a -> page-b -> page-a would loop forever.
        Livewire::actingAs($this->admin)
            ->test(CreateRedirect::class)
            ->fillForm(['from_path' => 'page-a', 'to_path' => '/page-b', 'status_code' => 301])
            ->call('create')
            ->assertHasFormErrors(['to_path']);

        $this->assertSame(1, Redirect::count());
    }

    public function test_a_non_cyclic_redirect_chain_is_allowed(): void
    {
        Redirect::create(['from_path' => 'page-b', 'to_path' => '/page-c', 'status_code' => 301]);

        Livewire::actingAs($this->admin)
            ->test(CreateRedirect::class)
            ->fillForm(['from_path' => 'page-a', 'to_path' => '/page-b', 'status_code' => 301])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Redirect::count());
    }

    public function test_editing_a_redirect_to_keep_the_same_to_path_is_not_flagged_as_its_own_cycle(): void
    {
        $redirect = Redirect::create(['from_path' => 'page-a', 'to_path' => '/pricing', 'status_code' => 301]);

        Livewire::actingAs($this->admin)
            ->test(EditRedirect::class, ['record' => $redirect->getRouteKey()])
            ->fillForm(['status_code' => 302])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(302, $redirect->fresh()->status_code);
    }

    public function test_a_redirect_pointing_to_an_external_url_is_never_flagged_as_a_cycle(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CreateRedirect::class)
            ->fillForm(['from_path' => 'old-affiliate-page', 'to_path' => 'https://external-site.example/offer', 'status_code' => 301])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Redirect::count());
    }

    public function test_turning_a_404_into_a_redirect_is_blocked_when_it_would_create_a_cycle(): void
    {
        Redirect::create(['from_path' => 'old-campaign-link', 'to_path' => '/dead-end', 'status_code' => 301]);
        // Deleting the redirect for the log's own path so the "existing
        // redirect" branch isn't hit — this exercises the fresh-create path.
        Redirect::where('from_path', 'old-campaign-link')->delete();

        Redirect::create(['from_path' => 'dead-end', 'to_path' => '/old-campaign-link', 'status_code' => 301]);

        $log = NotFoundLog::create([
            'path' => 'old-campaign-link',
            'hits_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListNotFoundLogs::class)
            ->callTableAction('createRedirect', $log, data: ['to_path' => '/dead-end', 'status_code' => 301]);

        // The would-be cyclic redirect (old-campaign-link -> dead-end,
        // completing dead-end -> old-campaign-link -> dead-end) must never
        // have been written.
        $this->assertSame(0, Redirect::where('from_path', 'old-campaign-link')->count());
    }

    public function test_a_non_admin_cannot_access_the_redirects_or_404_monitor_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)
            ->test(CreateRedirect::class)
            ->assertForbidden();
    }

    /**
     * Regression test for a real routing bug found in review: only five
     * SitePage slugs (about/terms/privacy/refund-policy/cookie-policy) had
     * their own dedicated route — the Filament "Site Pages" form has always
     * accepted any free-text slug ("The page URL, e.g. 'about' for
     * /about"), so an admin creating a sixth page had no live URL to reach
     * it at all; it fell straight through to the generic 404 fallback. The
     * new catch-all /{slug} route (PageController::show) fixes this.
     */
    public function test_an_admin_created_page_with_a_novel_slug_is_publicly_reachable(): void
    {
        SitePage::create([
            'slug' => 'shipping-policy',
            'title' => 'Shipping Policy',
            'content' => 'We ship worldwide.',
            'is_published' => true,
        ]);

        $response = $this->get('/shipping-policy');

        $response->assertOk();
        $response->assertSee('We ship worldwide.', false);
    }

    /**
     * The generic /{slug} route matches almost any single-segment path, so
     * a slug with no published page behind it must fall through to the
     * exact same redirect/404-monitor safety net as a path that matched no
     * route at all — not a bare framework 404 that bypasses both.
     */
    public function test_an_unknown_slug_still_goes_through_the_redirect_and_404_monitor_safety_net(): void
    {
        $response = $this->get('/does-not-exist-anywhere');

        $response->assertNotFound();
        $this->assertSame(1, NotFoundLog::where('path', 'does-not-exist-anywhere')->count());
    }

    public function test_a_redirect_for_a_single_segment_path_still_wins_over_the_generic_slug_route(): void
    {
        Redirect::create(['from_path' => 'old-single-page', 'to_path' => '/pricing', 'status_code' => 301]);

        $this->get('/old-single-page')->assertRedirect('/pricing')->assertStatus(301);
    }

    public function test_unpublishing_an_existing_site_page_still_goes_through_the_redirect_and_404_monitor_safety_net(): void
    {
        // 'terms' rather than 'about' — /about became a permanent,
        // always-rendered structural page (task #161, like /pricing or
        // /contact) whose content lives in about_* SiteSetting fields, not
        // this row, so unpublishing its SitePage row no longer 404s it.
        // See PageController::show()'s 'about' special case.
        $page = SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'Hello', 'is_published' => true]);
        $page->update(['is_published' => false]);

        $response = $this->get('/terms');

        $response->assertNotFound();
        $this->assertSame(1, NotFoundLog::where('path', 'terms')->count());
    }
}
