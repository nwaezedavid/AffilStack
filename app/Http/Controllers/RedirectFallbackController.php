<?php

namespace App\Http\Controllers;

use App\Services\Seo\SiteNotFoundResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The RankMath-style redirects manager's runtime half (see Redirect/
 * NotFoundLog and the Filament Redirects page) — registered as
 * Route::fallback() at the very end of routes/web.php, so it only ever
 * fires once every real route has already failed to match at all. The
 * actual "check for a redirect, else log the 404" logic lives in
 * SiteNotFoundResponder, shared with PageController::show() so a path that
 * matched the generic SitePage route but has no published page behind it
 * gets the exact same treatment.
 *
 * Deliberately skips the admin panel, API, and webhook paths — 404s there
 * are a developer/integration concern, not something the marketing site's
 * 404 monitor should collect or that a marketing-authored redirect should
 * ever apply to. 'afs-login' is the standalone login-redirect route (see
 * routes/web.php); 'afs-admin' is the actual Filament panel path
 * (AdminPanelProvider) — both are real, deliberately registered routes
 * this fallback should never shadow, and 'admin' is kept too as the old,
 * now-unused default Filament path, in case anything still probes it.
 */
class RedirectFallbackController extends Controller
{
    protected const EXCLUDED_PREFIXES = ['afs-login', 'afs-admin', 'admin', 'api', 'webhooks'];

    public function __construct(protected SiteNotFoundResponder $responder) {}

    public function handle(Request $request): RedirectResponse
    {
        $path = trim($request->path(), '/');

        if ($path === '' || $this->isExcluded($path)) {
            throw new NotFoundHttpException;
        }

        return $this->responder->respond($request, $path);
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
