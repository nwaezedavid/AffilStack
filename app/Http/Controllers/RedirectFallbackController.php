<?php

namespace App\Http\Controllers;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The RankMath-style redirects manager's runtime half (see Redirect/
 * NotFoundLog and the Filament Redirects page) — registered as
 * Route::fallback() at the very end of routes/web.php, so it only ever
 * fires once every real route has already failed to match: a genuine dead
 * link gets sent somewhere real instead of a bare 404, and the path is
 * logged so an admin can turn a real pattern of dead links into a redirect
 * from the dashboard.
 *
 * Deliberately skips the admin panel, API, and webhook paths — 404s there
 * are a developer/integration concern, not something the marketing site's
 * 404 monitor should collect or that a marketing-authored redirect should
 * ever apply to.
 */
class RedirectFallbackController extends Controller
{
    protected const EXCLUDED_PREFIXES = ['afs-login', 'admin', 'api', 'webhooks'];

    /**
     * redirects.from_path/not_found_logs.path/referer are plain varchar(255)
     * columns (SQLite, used in tests, has no length limit and silently
     * accepts anything — MySQL, used in production, does not). An
     * unauthenticated visitor's request path is untrusted input with no
     * upper bound of its own (a scanner or a malformed link can easily
     * exceed 255 characters), so it's truncated once, here, before it ever
     * reaches a database column. Capped well under 255 (not right at it) to
     * leave room for Redirect::forPath()'s own cache-key prefix
     * ("redirect:" plus the cache store's own key prefix) — the derived
     * cache key must fit the "cache" table's own varchar(255) "key" column
     * too, not just the redirects/not_found_logs tables.
     */
    protected const MAX_STORED_LENGTH = 200;

    public function handle(Request $request): RedirectResponse
    {
        $path = Str::limit(trim($request->path(), '/'), self::MAX_STORED_LENGTH, '');

        if ($path === '/' || $this->isExcluded($path)) {
            throw new NotFoundHttpException;
        }

        if ($redirect = Redirect::forPath($path)) {
            $redirect->recordHit();

            return redirect($redirect->to_path, $redirect->status_code);
        }

        $referer = $request->headers->get('referer');
        NotFoundLog::record($path, $referer ? Str::limit($referer, self::MAX_STORED_LENGTH, '') : null);

        throw new NotFoundHttpException;
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
}
