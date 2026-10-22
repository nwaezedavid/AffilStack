<?php

use App\Http\Middleware\AllowEmbedding;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\EnsureAccountNotSuspended;
use App\Http\Middleware\EnsureAdminApiToken;
use App\Http\Middleware\EnsureAppScope;
use App\Http\Middleware\EnsureDeveloperAuthenticated;
use App\Http\Middleware\EnsureDeveloperProfile;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureTokenScope;
use App\Http\Middleware\LocalizeResponse;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\MarkLocalizedUrl;
use App\Http\Middleware\MeterApiUsage;
use App\Http\Middleware\RecordPhpRuntime;
use App\Http\Middleware\RejectExecutableUploads;
use App\Http\Middleware\RestrictAffiliateOnlyAccounts;
use App\Http\Middleware\RestrictAgencySeats;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StripLocalePrefix;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only answer for the site's own host (and its www. form): links in
        // emails and the cached /embed/banner.js are built from the request
        // host, so a forged Host header must never be able to change them.
        // (No custom list: the default trusts exactly the APP_URL host and
        // its subdomains as an anchored pattern.)
        $middleware->trustHosts();

        $middleware->validateCsrfTokens(except: [
            // Server-to-server gateway webhooks — each controller verifies
            // the gateway's own signature before trusting anything. Paystack
            // and PayPal were missing here, so every one of their webhooks
            // got a 419 in production (CSRF is skipped under unit tests).
            'webhooks/flutterwave',
            'webhooks/stripe',
            'webhooks/paystack',
            'webhooks/paypal',
            'internal/analytics/view',
            'internal/analytics/duration',
        ]);

        // Pre-launch HawkScan fixes (security headers, X-Powered-By,
        // cookie flags, dotfile access) — see SecurityHeaders' own
        // docblock. Global (web + api) since every response needs them.
        $middleware->append(SecurityHeaders::class);

        // PHP2Shell hardening: the website records which PHP it runs on for
        // Tom's daily check, and no upload may carry server-side code (in
        // the groups, so a rejected form gets its error back on the page).
        $middleware->append(RecordPhpRuntime::class);
        $middleware->appendToGroup('web', RejectExecutableUploads::class);
        $middleware->appendToGroup('api', RejectExecutableUploads::class);

        // Feature 6: /fr/pricing is the French /pricing — the language code
        // comes off the address before routing (see StripLocalePrefix), and
        // every web page is shown in the visitor's language (LocalizeResponse).
        $middleware->prepend(StripLocalePrefix::class);

        $middleware->alias([
            'restrict-agency-seats' => RestrictAgencySeats::class,
            'restrict-affiliate-only' => RestrictAffiliateOnlyAccounts::class,
            'not-suspended' => EnsureAccountNotSuspended::class,
            'api-token-auth' => ApiTokenAuth::class,
            'admin-api-token' => EnsureAdminApiToken::class,
            'meter-api-usage' => MeterApiUsage::class,
            'token-scope' => EnsureTokenScope::class,
            'app-scope' => EnsureAppScope::class,
            'developer-auth' => EnsureDeveloperAuthenticated::class,
            'developer-profile' => EnsureDeveloperProfile::class,
            'idempotent' => EnsureIdempotency::class,
            'log-api-request' => LogApiRequest::class,
            'cache-public-page' => CachePublicPage::class,
            'allow-embedding' => AllowEmbedding::class,
            'localized-url' => MarkLocalizedUrl::class,
        ]);

        // Agent 03: keeps users.last_active_at fresh (throttled to one
        // write per user every few minutes) for the admin active/inactive
        // view and the re-engagement emails — see TrackUserActivity.
        $middleware->appendToGroup('web', TrackUserActivity::class);
        $middleware->appendToGroup('web', LocalizeResponse::class);

        // The Partner Portal has its own sign-in page on the affiliate host.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('partner.*') ? route('partner.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
