<?php

namespace App\Services\Seo;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The one place a real dead end on the public site gets handled — used by
 * both the catch-all Route::fallback() (RedirectFallbackController, for a
 * path that matched no route at all) and PageController::show() (for a
 * path that DID match the generic SitePage route but has no published page
 * behind it). Before this existed, PageController's branch just threw a
 * bare 404 straight past Redirect::forPath()/NotFoundLog::record() — an
 * admin-created page that got unpublished or renamed would silently lose
 * the same redirect/404-monitor safety net every other dead link gets.
 *
 * SiteLinkHealthChecker also drives this exact method to PROBE whether a
 * path resolves, by marking its synthetic request with the
 * X-Site-Health-Check header — that request must see the identical
 * redirect-or-404 outcome a real visitor would, but without counting as
 * one: a scan re-checking the same broken link every hour would otherwise
 * inflate NotFoundLog.hits_count/Redirect.hits_count on every run,
 * indistinguishable from real traffic.
 */
class SiteNotFoundResponder
{
    /**
     * redirects.from_path/not_found_logs.path/referer are plain varchar(255)
     * columns (SQLite, used in tests, has no length limit and silently
     * accepts anything — MySQL, used in production, does not). An
     * unauthenticated visitor's request path is untrusted input with no
     * upper bound of its own, so it's truncated once, here, before it ever
     * reaches a database column. Capped well under 255 (not right at it) to
     * leave room for Redirect::forPath()'s own cache-key prefix
     * ("redirect:" plus the cache store's own key prefix).
     */
    public const MAX_STORED_LENGTH = 200;

    public function respond(Request $request, string $path): RedirectResponse
    {
        $path = Str::limit(trim($path, '/'), self::MAX_STORED_LENGTH, '');
        $isHealthCheckProbe = $request->headers->has('X-Site-Health-Check');

        if ($redirect = Redirect::forPath($path)) {
            if (! $isHealthCheckProbe) {
                $redirect->recordHit();
            }

            return redirect($redirect->to_path, $redirect->status_code);
        }

        if (! $isHealthCheckProbe) {
            $referer = $request->headers->get('referer');
            NotFoundLog::record($path, $referer ? Str::limit($referer, self::MAX_STORED_LENGTH, '') : null);
        }

        throw new NotFoundHttpException;
    }
}
