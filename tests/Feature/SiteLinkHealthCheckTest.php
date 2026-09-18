<?php

namespace Tests\Feature;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SitePage;
use App\Services\Seo\SiteLinkHealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The active/proactive half of the 404 monitor (see
 * RedirectsAndNotFoundMonitorTest for the passive, visitor-triggered half).
 * SiteLinkHealthChecker scans admin-authored content and existing
 * redirects for internal links that no longer resolve, fixing only the
 * unambiguous cases automatically and leaving everything else for a
 * super-admin to review in the 404 Monitor — never guessing.
 */
class SiteLinkHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_link_to_a_renamed_page_is_auto_fixed_when_the_match_is_unambiguous(): void
    {
        SitePage::create(['slug' => 'refund-policy', 'title' => 'Refund Policy', 'content' => 'See our <a href="/refund-polcy">refund policy</a>.', 'is_published' => true]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(1, $stats['auto_fixed']);
        $redirect = Redirect::where('from_path', 'refund-polcy')->first();
        $this->assertNotNull($redirect);
        $this->assertSame('/refund-policy', $redirect->to_path);
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_a_link_with_only_a_case_difference_is_auto_fixed(): void
    {
        SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'Read the <a href="/Terms">terms</a>.', 'is_published' => true]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(1, $stats['auto_fixed']);
        $this->assertNotNull(Redirect::where('from_path', 'Terms')->first());
    }

    public function test_a_broken_link_with_no_close_match_is_flagged_for_manual_review_instead_of_guessed(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Check out <a href="/completely-unrelated-page">this</a>.', 'is_published' => true]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(0, $stats['auto_fixed']);
        $this->assertSame(1, $stats['flagged']);
        $this->assertSame(0, Redirect::count());

        $log = NotFoundLog::where('path', 'completely-unrelated-page')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Site Health Scan', (string) $log->referer);
        $this->assertStringContainsString('About', (string) $log->referer);
    }

    public function test_a_broken_link_with_more_than_one_equally_close_match_is_never_guessed(): void
    {
        SitePage::create(['slug' => 'terms-of-service', 'title' => 'Terms of Service', 'content' => 'x', 'is_published' => true]);
        SitePage::create(['slug' => 'terms-of-services', 'title' => 'Terms of Services', 'content' => 'x', 'is_published' => true]);
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'See <a href="/term-of-service">this</a>.', 'is_published' => true]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        // "term-of-service" is a close (>=90%) fuzzy match to BOTH real
        // slugs above — genuinely ambiguous, so it must be flagged rather
        // than redirected to a guess.
        $this->assertSame(0, $stats['auto_fixed']);
        $this->assertGreaterThanOrEqual(1, $stats['flagged']);
        $this->assertSame(0, Redirect::where('from_path', 'term-of-service')->count());
    }

    public function test_a_working_link_is_left_completely_untouched(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Read our <a href="/terms">terms</a>.', 'is_published' => true]);
        SitePage::create(['slug' => 'terms', 'title' => 'Terms', 'content' => 'Body', 'is_published' => true]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(0, $stats['broken']);
        $this->assertSame(0, $stats['auto_fixed']);
        $this->assertSame(0, $stats['flagged']);
        $this->assertSame(0, Redirect::count());
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_an_existing_redirect_pointing_at_a_now_dead_page_is_flagged_as_a_broken_chain(): void
    {
        Redirect::create(['from_path' => 'old-promo', 'to_path' => '/a-page-that-was-deleted', 'status_code' => 301]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(1, $stats['flagged']);
        $log = NotFoundLog::where('path', 'a-page-that-was-deleted')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('old-promo', (string) $log->referer);
        // Regression guard: the checker's own probe of "old-promo" (to see
        // where it leads) must not itself count as a hit on that redirect.
        $this->assertSame(0, Redirect::where('from_path', 'old-promo')->value('hits_count'));
        $this->assertSame(1, $log->hits_count);
    }

    public function test_links_under_excluded_prefixes_are_never_checked_or_flagged(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'Nothing to see: <a href="/admin/secret">here</a>.', 'is_published' => true]);

        $stats = app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(0, $stats['checked']);
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_running_the_scan_twice_does_not_duplicate_a_flagged_broken_link(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'See <a href="/nowhere-real">this</a>.', 'is_published' => true]);

        app(SiteLinkHealthChecker::class)->run();
        app(SiteLinkHealthChecker::class)->run();

        $this->assertSame(1, NotFoundLog::where('path', 'nowhere-real')->count());
        $this->assertSame(2, NotFoundLog::where('path', 'nowhere-real')->value('hits_count'));
    }

    public function test_the_artisan_command_reports_a_summary(): void
    {
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'x', 'is_published' => true]);

        $this->artisan('seo:check-links')->assertSuccessful();
    }

    public function test_probing_a_link_during_a_scan_never_inflates_real_hit_counters(): void
    {
        Redirect::create(['from_path' => 'old-promo', 'to_path' => '/about', 'status_code' => 301]);
        SitePage::create(['slug' => 'about', 'title' => 'About', 'content' => 'x', 'is_published' => true]);

        app(SiteLinkHealthChecker::class)->run();

        // The checker's resolves() check dispatches a real in-process
        // request to see where "/old-promo" leads — that probe must not
        // look like a genuine visitor hit on the redirect it's inspecting.
        $this->assertSame(0, Redirect::where('from_path', 'old-promo')->value('hits_count'));
    }
}
