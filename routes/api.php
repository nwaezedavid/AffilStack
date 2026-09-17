<?php

use App\Http\Controllers\Api\ExtensionApiController;
use App\Http\Controllers\Api\V1\Admin\AdminApiController;
use App\Http\Controllers\Api\V1\CrmContactsController;
use App\Http\Controllers\Api\V1\GenerationsController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OffersController;
use App\Http\Controllers\Api\V1\ReferralsController;
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

// The general-purpose API (task #6) — one bearer token (dashboard: "API
// Access", or the browser extension's own token — either works) scoped to
// exactly what its owner can already see, via the same visibleOffers()/
// visibleGenerations()/Offer::isAccessibleBy() the dashboard itself uses.
// throttle:60,1 is a first, deliberately conservative rate limit; raise it
// per-token later if a real integration needs more.
Route::prefix('v1')->middleware(['api-token-auth', 'throttle:60,1'])->group(function () {
    Route::get('/me', [MeController::class, 'show'])->name('api.v1.me');

    Route::get('/offers', [OffersController::class, 'index'])->name('api.v1.offers.index');
    Route::post('/offers', [OffersController::class, 'store'])->name('api.v1.offers.store');
    Route::get('/offers/{offer}', [OffersController::class, 'show'])->name('api.v1.offers.show');

    Route::get('/generations', [GenerationsController::class, 'index'])->name('api.v1.generations.index');
    Route::get('/generations/{generation}', [GenerationsController::class, 'show'])->name('api.v1.generations.show');

    Route::get('/crm-contacts', [CrmContactsController::class, 'index'])->name('api.v1.crm-contacts.index');
    Route::post('/crm-contacts', [CrmContactsController::class, 'store'])->name('api.v1.crm-contacts.store');
    Route::get('/crm-contacts/{contact}', [CrmContactsController::class, 'show'])->name('api.v1.crm-contacts.show');
    Route::patch('/crm-contacts/{contact}', [CrmContactsController::class, 'update'])->name('api.v1.crm-contacts.update');

    Route::get('/referrals/summary', [ReferralsController::class, 'summary'])->name('api.v1.referrals.summary');

    // Admin-only sub-surface: requires a token minted specifically for a
    // full admin (Filament: System > API Tokens) — see EnsureAdminApiToken.
    Route::prefix('admin')->middleware('admin-api-token')->group(function () {
        Route::get('/users', [AdminApiController::class, 'users'])->name('api.v1.admin.users');
        Route::get('/plans', [AdminApiController::class, 'plans'])->name('api.v1.admin.plans');
        Route::get('/referral-payouts', [AdminApiController::class, 'referralPayouts'])->name('api.v1.admin.referral-payouts');
        Route::get('/revenue', [AdminApiController::class, 'revenue'])->name('api.v1.admin.revenue');
    });
});
