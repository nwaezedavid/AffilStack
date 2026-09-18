<?php

namespace App\Services\Seo;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

/**
 * The active half of the 404 monitor: rather than only reacting once a real
 * visitor hits a dead link (RedirectFallbackController/NotFoundLog), this
 * proactively crawls the site's own admin-authored content for internal
 * links that no longer resolve, and for every existing Redirect whose own
 * target has gone dead ("broken redirect chain").
 *
 * Deliberately conservative about what it fixes on its own: a broken link
 * only gets an automatic Redirect when the fix is unambiguous — the exact
 * same page under a different case/punctuation, or a single page whose
 * slug is an unmistakably close match. Anything less certain is left for a
 * super-admin to turn into a redirect from the 404 Monitor dashboard
 * (NotFoundLogsTable's existing "Create redirect" action) rather than
 * guessing and silently sending a visitor to the wrong page. See
 * app/Console/Commands/CheckSiteLinkHealth.php for how this is scheduled.
 */
class SiteLinkHealthChecker
{
    /** Mirrors RedirectFallbackController::EXCLUDED_PREFIXES — a scanned
     * path under one of these is a developer/integration concern, never
     * something this checker should touch. */
    protected const EXCLUDED_PREFIXES = ['afs-login', 'admin', 'api', 'webhooks'];

    /** A redirect chain longer than this is treated as broken rather than
     * followed forever — matches Redirect::wouldCreateCycle()'s own bound. */
    protected const MAX_HOPS = 5;

    /** How close a broken path's slug must be to a single known-good page
     * before this auto-creates a redirect to it, as a similar_text()
     * percentage. Deliberately high — this is the "conservative" tier. */
    protected const FUZZY_MATCH_THRESHOLD = 90.0;

    /**
     * @return array{checked: int, broken: int, auto_fixed: int, flagged: int}
     */
    public function run(): array
    {
        $knownGoodPaths = $this->gatherKnownGoodPaths();
        $candidates = $this->gatherCandidateLinks($knownGoodPaths);

        $stats = ['checked' => 0, 'broken' => 0, 'auto_fixed' => 0, 'flagged' => 0];

        foreach ($candidates as $path => $source) {
            $stats['checked']++;

            if ($this->resolves($path)) {
                continue;
            }

            $stats['broken']++;

            $fix = $this->findConfidentFix($path, $knownGoodPaths);

            if ($fix !== null && ! Redirect::wouldCreateCycle($path, "/{$fix}")) {
                Redirect::updateOrCreate(
                    ['from_path' => $path],
                    ['to_path' => "/{$fix}", 'status_code' => 301],
                );
                $stats['auto_fixed']++;

                continue;
            }

            NotFoundLog::record(
                Str::limit($path, SiteNotFoundResponder::MAX_STORED_LENGTH, ''),
                Str::limit("[Site Health Scan] {$source}", SiteNotFoundResponder::MAX_STORED_LENGTH, ''),
            );
            $stats['flagged']++;
        }

        return $stats;
    }

    /**
     * Paths guaranteed to work by construction — a handful of named public
     * routes plus every currently-published SitePage slug (now that the
     * generic /{slug} route exists, any such slug really is reachable).
     * Used both to skip probing things we already know are fine and as the
     * candidate pool for fuzzy-matching a broken link's likely fix.
     *
     * @return Collection<int, string>
     */
    protected function gatherKnownGoodPaths(): Collection
    {
        $paths = collect([
            route('home'), route('help.index'), route('tutorials.index'),
            route('registration.pricing'), route('contact.show'),
        ])->map(fn (string $url) => Redirect::normalizeInternalPath($url))->filter();

        if (SiteSetting::flag('affiliate_program_enabled')) {
            $paths->push(Redirect::normalizeInternalPath(route('affiliate.landing')));
        }

        $sitePageSlugs = SitePage::where('is_published', true)->pluck('slug');

        return $paths->merge($sitePageSlugs)->filter()->unique()->values();
    }

    /**
     * Every internal link this checker can actually discover without a
     * real browser: hrefs embedded in published page content, and the
     * target of every configured Redirect (so a redirect whose own
     * destination has since gone dead gets caught too, not just links
     * visitors click).
     *
     * @param  Collection<int, string>  $knownGoodPaths
     * @return Collection<string, string> normalized path => human-readable source
     */
    protected function gatherCandidateLinks(Collection $knownGoodPaths): Collection
    {
        $candidates = collect();

        foreach (SitePage::where('is_published', true)->get(['title', 'slug', 'content']) as $page) {
            foreach ($this->extractHrefs($page->content) as $href) {
                $this->addCandidate($candidates, $href, "Found in page \"{$page->title}\" (/{$page->slug})", $knownGoodPaths);
            }
        }

        foreach (Redirect::all(['from_path', 'to_path']) as $redirect) {
            $this->addCandidate($candidates, $redirect->to_path, "Redirect from \"/{$redirect->from_path}\" points here", $knownGoodPaths);
        }

        return $candidates;
    }

    protected function addCandidate(Collection $candidates, string $href, string $source, Collection $knownGoodPaths): void
    {
        $path = Redirect::normalizeInternalPath($href);

        if ($path === null || $path === '' || $this->isExcluded($path) || $knownGoodPaths->contains($path)) {
            return;
        }

        // First source found for a given path wins — plenty to act on
        // without tracking every place a dead link is referenced from.
        if (! $candidates->has($path)) {
            $candidates->put($path, $source);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function extractHrefs(?string $html): array
    {
        if (! $html) {
            return [];
        }

        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $matches);

        return $matches[1] ?? [];
    }

    protected function isExcluded(string $path): bool
    {
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, "{$prefix}/")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dispatches an in-process request through the app's own HTTP kernel —
     * no real network round trip, works identically in every environment
     * this runs in. Follows same-host redirects up to MAX_HOPS to see
     * where a chain actually ends up; an off-site redirect (Location
     * pointing at another host) is treated as resolved, since it's left
     * this app's own link graph entirely.
     */
    protected function resolves(string $path, int $hops = 0): bool
    {
        if ($hops >= self::MAX_HOPS) {
            return false;
        }

        $request = Request::create('/'.$path, 'GET');
        $request->headers->set('X-Site-Health-Check', '1');

        $response = App::make(Kernel::class)->handle($request);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return true;
        }

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $next = Redirect::normalizeInternalPath((string) $response->headers->get('Location'));

            return $next === null || $this->resolves($next, $hops + 1);
        }

        return false;
    }

    /**
     * The conservative auto-fix policy: only ever returns a match when
     * there is exactly one unambiguous candidate, never the "closest"
     * option among several plausible ones.
     *
     * @param  Collection<int, string>  $knownGoodPaths
     */
    protected function findConfidentFix(string $brokenPath, Collection $knownGoodPaths): ?string
    {
        $normalizedBroken = Str::slug($brokenPath);

        $exactSlugMatches = $knownGoodPaths->filter(fn (string $good) => Str::slug($good) === $normalizedBroken);

        if ($exactSlugMatches->count() === 1) {
            return $exactSlugMatches->first();
        }

        if ($exactSlugMatches->count() > 1) {
            return null; // ambiguous — more than one page slugifies the same way
        }

        $closeMatches = $knownGoodPaths->filter(function (string $good) use ($brokenPath) {
            similar_text($brokenPath, $good, $percent);

            return $percent >= self::FUZZY_MATCH_THRESHOLD;
        });

        return $closeMatches->count() === 1 ? $closeMatches->first() : null;
    }
}
