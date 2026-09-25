<?php

use App\Http\Controllers\Api\ExtensionApiController;
use App\Http\Controllers\Api\V1\Admin\AdminApiController;
use App\Http\Controllers\Api\V1\CrmContactsController;
use App\Http\Controllers\Api\V1\GenerationsController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OffersController;
use App\Http\Controllers\Api\V1\OpenApiSpecController;
use App\Http\Controllers\Api\V1\ReferralsController;
use App\Http\Controllers\Api\V1\ZapierSubscriptionsController;
use Illuminate\Support\Facades\Route;

// The browser capture extension's entire backend surface (item 11).
// Bearer-token authenticated — see App\Http\Middleware\ApiTokenAuth — never
// session/CSRF based, since an extension runs in its own
// chrome-extension:// origin. Left untouched by the general API below —
// same ApiToken model, but its own unprefixed routes for backward
// compatibility with the shipped extension.
Route::middleware('api-token-auth')->group(function () {
    Route::get('/me', [ExtensionApiController::class, 'me'])->name('api.me');
    Route::get('/offers', [ExtensionApiController::class, 'offers'])->name('api.offers');
    Route::post('/clips', [ExtensionApiController::class, 'storeClip'])->name('api.clips.store');
});

// API roadmap item #4 — publicly readable (no auth, no secrets in it) so
// Postman/Insomnia and the JS/Python SDKs can import it directly, the same
// way Stripe/Twilio publish theirs. Deliberately outside the v1 group
// below so it never inherits api-token-auth.
Route::get('/v1/openapi.yaml', [OpenApiSpecController::class, 'show'])->name('api.v1.openapi');

// The general-purpose API (task #6) — one bearer token (dashboard: "API
// Access", or the browser extension's own token — either works) scoped to
// exactly what its owner can already see, via the same visibleOffers()/
// visibleGenerations()/Offer::isAccessibleBy() the dashboard itself uses.
//
// log-api-request (roadmap item #7) wraps outside everything else in this
// group so it always logs the final response — including one token-scope
// or throttle:api itself rejects. token-scope (item #2) blocks a
// read-only token's writes. throttle:api (item #3) replaces the old flat
// throttle:60,1 with config('api_billing.rate_limits')'s per-plan limits.
Route::prefix('v1')->middleware(['api-token-auth', 'log-api-request', 'throttle:api', 'token-scope'])->group(function () {
    Route::get('/me', [MeController::class, 'show'])->name('api.v1.me');

    Route::get('/offers', [OffersController::class, 'index'])->name('api.v1.offers.index');
    // Metered — see config('api_billing.costs') and MeterApiUsage (which
    // skips the charge entirely for a sandbox token — item #5). idempotent
    // (item #1) runs first so a replayed call short-circuits before it
    // could ever be billed again.
    Route::post('/offers', [OffersController::class, 'store'])->middleware(['idempotent', 'meter-api-usage'])->name('api.v1.offers.store');
    Route::get('/offers/{offer}', [OffersController::class, 'show'])->name('api.v1.offers.show');

    Route::get('/generations', [GenerationsController::class, 'index'])->name('api.v1.generations.index');
    Route::get('/generations/{generation}', [GenerationsController::class, 'show'])->name('api.v1.generations.show');

    Route::get('/crm-contacts', [CrmContactsController::class, 'index'])->name('api.v1.crm-contacts.index');
    Route::post('/crm-contacts', [CrmContactsController::class, 'store'])->middleware('idempotent')->name('api.v1.crm-contacts.store');
    // Bulk create (API roadmap item #6) — deliberately not wrapped in
    // idempotent: a batch of N contacts isn't a single retryable operation
    // the same way one create is, and bulkStore()'s partial-success
    // response (created/failed, by index) already makes a safe retry of
    // just the failed items straightforward without it.
    Route::post('/crm-contacts/bulk', [CrmContactsController::class, 'bulkStore'])->name('api.v1.crm-contacts.bulk-store');
    Route::get('/crm-contacts/{contact}', [CrmContactsController::class, 'show'])->name('api.v1.crm-contacts.show');
    Route::patch('/crm-contacts/{contact}', [CrmContactsController::class, 'update'])->name('api.v1.crm-contacts.update');

    Route::get('/referrals/summary', [ReferralsController::class, 'summary'])->name('api.v1.referrals.summary');

    // API roadmap item #9 — backs the Zapier platform app's REST Hook
    // triggers (see integrations/zapier/triggers/*.js). Zapier calls these
    // with the user's own AffilStack token to subscribe/unsubscribe its
    // catch-hook URL, reusing WebhookEndpoint under the hood so a user can
    // see and revoke their own Zapier connection right on the API Access
    // page like any other webhook endpoint.
    Route::prefix('zapier')->group(function () {
        Route::post('/subscriptions', [ZapierSubscriptionsController::class, 'store'])->name('api.v1.zapier.subscriptions.store');
        Route::delete('/subscriptions/{webhook}', [ZapierSubscriptionsController::class, 'destroy'])->name('api.v1.zapier.subscriptions.destroy');
    });

    // Admin-only sub-surface: requires a token minted specifically for a
    // full admin (Filament: System > API Tokens) — see EnsureAdminApiToken.
    Route::prefix('admin')->middleware('admin-api-token')->group(function () {
        Route::get('/users', [AdminApiController::class, 'users'])->name('api.v1.admin.users');
        Route::get('/plans', [AdminApiController::class, 'plans'])->name('api.v1.admin.plans');
        Route::get('/referral-payouts', [AdminApiController::class, 'referralPayouts'])->name('api.v1.admin.referral-payouts');
        Route::get('/revenue', [AdminApiController::class, 'revenue'])->name('api.v1.admin.revenue');
    });
});
