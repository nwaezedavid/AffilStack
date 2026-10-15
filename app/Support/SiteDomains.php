<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The public website and the customer app on separate hosts.
 *
 * With APP_DOMAIN set (e.g. app.affilstack.com), sign-up, login, the
 * customer dashboard and the admin panel answer only on that host, the
 * public website only on the APP_URL host (affilstack.com), and a few
 * shared routes (webhooks, tracked links, email tracking, favicons,
 * beacons, banner embeds, the API) on both. Without it, everything is one
 * host exactly as before, which is also how the test suite runs.
 *
 * Each host keeps its own host-only session cookie, so the WordPress blog
 * and other subdomains never receive a customer's session. The website
 * learns that a visitor is signed in from a small "signed in" hint cookie
 * set on the parent domain at login (see SignedInHint), which carries no
 * credentials: it only swaps "Login / Get started" for "Dashboard".
 */
class SiteDomains
{
    public static function marketingHost(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    public static function appHost(): ?string
    {
        $host = strtolower(trim((string) config('app.app_domain')));
        $host = (string) preg_replace('#^https?://#', '', $host);
        $host = rtrim(explode('/', $host)[0], '.');

        return $host !== '' && $host !== strtolower(static::marketingHost()) ? $host : null;
    }

    public static function isSplit(): bool
    {
        return static::appHost() !== null;
    }

    public static function appUrl(): ?string
    {
        $host = static::appHost();

        if ($host === null) {
            return null;
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$host;
    }

    /**
     * An address on the public website (affilstack.com), whichever host
     * the current request came in on — admin pages run on the app host
     * but describe the public site (SEO previews, webmaster tools, API
     * base address).
     */
    public static function publicUrl(string $path = '/'): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Register public-website routes (the APP_URL host only, when split).
     */
    public static function marketing(Closure $routes): void
    {
        static::isSplit()
            ? Route::domain(static::marketingHost())->group($routes)
            : Route::group([], $routes);
    }

    /**
     * Register customer-app routes (the APP_DOMAIN host only, when split).
     */
    public static function app(Closure $routes): void
    {
        static::isSplit()
            ? Route::domain((string) static::appHost())->group($routes)
            : Route::group([], $routes);
    }

    public static function isAppRequest(?Request $request = null): bool
    {
        $request ??= request();

        return static::isSplit() && strtolower($request->getHost()) === static::appHost();
    }

    /**
     * Whether the visitor is signed in, as far as the public website needs
     * to know (header buttons, calls to action).
     */
    public static function visitorIsSignedIn(): bool
    {
        if (auth()->check()) {
            return true;
        }

        return static::isSplit() && SignedInHint::present(request());
    }
}
