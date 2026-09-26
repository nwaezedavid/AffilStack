<?php

use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\CachePublicPage;
use App\Http\Middleware\EnsureAccountNotSuspended;
use App\Http\Middleware\EnsureAdminApiToken;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureTokenScope;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\MeterApiUsage;
use App\Http\Middleware\RestrictAffiliateOnlyAccounts;
use App\Http\Middleware\RestrictAgencySeats;
use App\Http\Middleware\SecurityHeaders;
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

        $middleware->alias([
            'restrict-agency-seats' => RestrictAgencySeats::class,
            'restrict-affiliate-only' => RestrictAffiliateOnlyAccounts::class,
            'not-suspended' => EnsureAccountNotSuspended::class,
            'api-token-auth' => ApiTokenAuth::class,
            'admin-api-token' => EnsureAdminApiToken::class,
            'meter-api-usage' => MeterApiUsage::class,
            'token-scope' => EnsureTokenScope::class,
            'idempotent' => EnsureIdempotency::class,
            'log-api-request' => LogApiRequest::class,
            'cache-public-page' => CachePublicPage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
