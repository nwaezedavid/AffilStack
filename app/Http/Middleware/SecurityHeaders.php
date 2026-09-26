<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the response-level fixes from the pre-launch HawkScan pass
 * (StackHawk scan of the affilstack-app branch): every one of these is a
 * global, per-response concern rather than a single route's job, so they
 * live in one place instead of being sprinkled across controllers.
 *
 * - Missing Anti-clickjacking Header / CSP Header Not Set: sets
 *   X-Frame-Options and a real (if intentionally permissive) CSP on every
 *   response. `unsafe-inline`/`unsafe-eval` stay in until the app's
 *   Blade/Alpine/Livewire/Filament inline scripts and styles are audited
 *   and moved behind nonces — tightening this without that audit risks
 *   silently breaking the admin panel or dashboard on a live site.
 * - Server Leaks Information via X-Powered-By: PHP's `expose_php` INI
 *   setting can't be flipped at runtime, and the target web server on
 *   Hostinger isn't guaranteed to strip it either, so it's removed here
 *   unconditionally.
 * - Cookie No HttpOnly Flag: flagged on XSRF-TOKEN. Laravel's CSRF
 *   middleware always queues that cookie non-HttpOnly on the assumption a
 *   frontend reads it via JS (e.g. axios' X-XSRF-TOKEN convention) — this
 *   app has no such JS (Blade forms use the @csrf hidden input instead),
 *   so nothing depends on client-side access and every outgoing cookie is
 *   forced HttpOnly here.
 * - .htaccess Information Leak: HawkScan fetched /.htaccess successfully
 *   (the dev server has no dotfile restriction of its own). Apache is
 *   covered separately in public/.htaccess; this is the same guard for
 *   any server in front of the app that doesn't block dotfiles itself.
 *   isDotfileRequest() blocks any path segment starting with a dot — not
 *   just the exact names HawkScan happened to probe — so it also covers
 *   .env.backup, .env.production, and any other dotfile that might end up
 *   web-readable (e.g. an editor swap file), the same way public/.htaccess's
 *   generic `<FilesMatch "^\.">` rule already does for Apache.
 *
 * Left alone, with reasoning recorded rather than "fixed": the bearer-token
 * `/api/v1/*` CORS wildcard (App\Http\Middleware\ApiTokenAuth is the actual
 * gate; no session cookie is ever readable cross-origin there), the emails
 * shown on the Contact/Profile pages (the feature, not a leak), and the
 * OPTIONS/redirect-body findings (standard Laravel routing/error-page
 * behaviour, no sensitive content in either body).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isDotfileRequest($request)) {
            abort(404);
        }

        $response = $next($request);

        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Allow-lists the third parties the site genuinely loads: the
        // admin-configured GA/GTM/Meta/TikTok tags (partials/tracking-*),
        // YouTube thumbnails and embeds (hero/feature/tutorial videos), and
        // externally hosted images/video. The previous self-only policy
        // silently blocked every one of those in production.
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com https://connect.facebook.net https://analytics.tiktok.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com https://connect.facebook.net https://www.facebook.com https://analytics.tiktok.com",
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://www.googletagmanager.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]));
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isHttpOnly()) {
                continue;
            }

            $response->headers->setCookie(Cookie::create(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                true,
                $cookie->isRaw(),
                $cookie->getSameSite(),
            ));
        }

        return $response;
    }

    private function isDotfileRequest(Request $request): bool
    {
        foreach (explode('/', $request->path()) as $segment) {
            if ($segment !== '' && str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }
}
