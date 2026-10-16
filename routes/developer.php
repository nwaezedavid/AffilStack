<?php

use App\Http\Controllers\Developer\AddonController;
use App\Http\Controllers\Developer\AuthController;
use App\Http\Controllers\Developer\EarningsController;
use App\Http\Controllers\Developer\PortalController;
use App\Http\Controllers\Developer\ProfileController;
use App\Support\SiteDomains;
use Illuminate\Support\Facades\Route;

/*
| Feature 7: the developer portal on developer.affilstack.com (or under
| /developers on the app when DEVELOPER_SUBDOMAIN is unset). Developers
| sign in with their AffilStack account: on its own host the portal asks
| the app to vouch for them (see Developer\AuthController).
*/
SiteDomains::developer(function () {
    Route::get('/', [PortalController::class, 'home'])->name('developer.home');
    Route::get('/docs/{page?}', [PortalController::class, 'docs'])->where('page', '[a-z0-9-]{1,40}')->name('developer.docs');

    Route::get('/sign-in', [AuthController::class, 'start'])->name('developer.login');
    Route::get('/sso/callback', [AuthController::class, 'callback'])->middleware('throttle:20,1,developer.sso')->name('developer.sso.callback');
    Route::post('/sign-out', [AuthController::class, 'logout'])->name('developer.logout');

    Route::middleware(['developer-auth', 'not-suspended'])->group(function () {
        Route::get('/join', [ProfileController::class, 'create'])->name('developer.join');
        Route::post('/join', [ProfileController::class, 'store'])->middleware('throttle:10,1,developer.join')->name('developer.join.store');

        Route::middleware('developer-profile')->group(function () {
            Route::get('/dashboard', [AddonController::class, 'index'])->name('developer.dashboard');
            Route::get('/addons/create', [AddonController::class, 'create'])->name('developer.addons.create');
            Route::post('/addons', [AddonController::class, 'store'])->middleware('throttle:20,1,developer.addons.store')->name('developer.addons.store');
            Route::get('/addons/{addon}', [AddonController::class, 'show'])->name('developer.addons.show');
            Route::get('/addons/{addon}/edit', [AddonController::class, 'edit'])->name('developer.addons.edit');
            Route::put('/addons/{addon}', [AddonController::class, 'update'])->name('developer.addons.update');
            Route::post('/addons/{addon}/submit', [AddonController::class, 'submit'])->middleware('throttle:20,1,developer.addons.submit')->name('developer.addons.submit');
            Route::post('/addons/{addon}/withdraw', [AddonController::class, 'withdraw'])->name('developer.addons.withdraw');
            Route::post('/addons/{addon}/secret', [AddonController::class, 'regenerateSecret'])->middleware('throttle:10,1,developer.addons.secret')->name('developer.addons.secret');
            Route::post('/addons/{addon}/retire', [AddonController::class, 'retire'])->name('developer.addons.retire');

            Route::get('/earnings', [EarningsController::class, 'index'])->name('developer.earnings');
            Route::post('/earnings/payout-method', [EarningsController::class, 'saveMethod'])->middleware('throttle:10,1,developer.payout-method')->name('developer.payout-method');
            Route::post('/earnings/resolve-account', [EarningsController::class, 'resolveAccount'])->middleware('throttle:15,1,developer.payout-resolve')->name('developer.payout-resolve');
            Route::post('/earnings/request', [EarningsController::class, 'requestPayout'])->middleware('throttle:10,1,developer.payout.request')->name('developer.payout.request');

            Route::get('/profile', [ProfileController::class, 'edit'])->name('developer.profile');
            Route::put('/profile', [ProfileController::class, 'update'])->name('developer.profile.update');
        });
    });
});
