<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit item #7 (caching/performance) — lets a browser (and any CDN in
 * front of the app) reuse a public marketing page for a few minutes instead
 * of round-tripping through Laravel on every single visit. Only ever
 * applied to routes with no per-visitor content and no form to CSRF-protect
 * (see routes/web.php) — /contact is deliberately excluded from this group
 * because it has one.
 *
 * Still not safe to mark cacheable when a flash message is present: the
 * shared marketing layout renders session('success')/session('error') on
 * every page (see layouts/marketing.blade.php), and a response carrying one
 * visitor's flash is never safe for a public cache to hand to a different
 * visitor. In that case this simply leaves Laravel's normal uncached
 * response alone.
 */
class CachePublicPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->method() !== 'GET' || ! $response->isSuccessful()) {
            return $response;
        }

        if ($request->session()->has('success') || $request->session()->has('error')) {
            return $response;
        }

        // private, not public: every response also carries this visitor's
        // session cookie, and a shared cache (LiteSpeed Cache, a CDN) that
        // honoured "public" could hand one visitor's session to everyone.
        // The browser still reuses the page for a few minutes.
        $response->headers->set('Cache-Control', 'private, max-age=300');

        return $response;
    }
}
