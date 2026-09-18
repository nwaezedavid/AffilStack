<?php

namespace App\Http\Controllers;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    protected const EXCLUDED_PREFIXES = ['afs-login', 'api', 'webhooks'];

    public function handle(Request $request): RedirectResponse
    {
        $path = trim($request->path(), '/');

        if ($path === '/' || $this->isExcluded($path)) {
            throw new NotFoundHttpException;
        }

        if ($redirect = Redirect::forPath($path)) {
            $redirect->recordHit();

            return redirect($redirect->to_path, $redirect->status_code);
        }

        NotFoundLog::record($path, $request->headers->get('referer'));

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
