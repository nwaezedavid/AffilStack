<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AffiliateSetPasswordController;
use App\Http\Controllers\Partner\AccountController;
use App\Http\Controllers\Partner\AuthController;
use App\Http\Controllers\Partner\BannersController;
use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\LeaderboardController;
use App\Http\Controllers\Partner\LinksController;
use App\Http\Controllers\Partner\NewsController;
use App\Http\Controllers\Partner\PasswordResetController;
use App\Http\Controllers\Partner\PayoutsController;
use App\Http\Controllers\Partner\PromoteController;
use App\Http\Middleware\EnsurePartnerAccount;
use App\Support\SiteDomains;
use Illuminate\Support\Facades\Route;

/*
| The affiliate program on its own host (affiliate.affilstack.com) — or
| under /affiliate on the website when AFFILIATE_SUBDOMAIN is unset. Has
| its own session (host-only cookie), its own sign-in with optional
| authenticator-app two-factor, and only ever admits affiliate-only
| partner accounts; customers keep their Refer & Earn page in the app.
*/
SiteDomains::affiliate(function () {
    Route::get('/', [AffiliateController::class, 'show'])->name('affiliate.landing');
    Route::post('/', [AffiliateController::class, 'apply'])->middleware('throttle:5,1,affiliate.apply')->name('affiliate.apply');

    Route::get('/set-password/{application}', [AffiliateSetPasswordController::class, 'show'])->name('affiliate.set-password.show');
    Route::post('/set-password/{application}', [AffiliateSetPasswordController::class, 'store'])->middleware('throttle:10,1,affiliate.set-password.store')->name('affiliate.set-password.store');

    Route::get('/login', [AuthController::class, 'create'])->name('partner.login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1,partner.login')->name('partner.login.store');
    Route::get('/two-factor', [AuthController::class, 'challenge'])->name('partner.two-factor');
    Route::post('/two-factor', [AuthController::class, 'verify'])->middleware('throttle:5,1,partner.two-factor')->name('partner.two-factor.store');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('partner.logout');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('partner.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1,partner.password.email')->name('partner.password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('partner.password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1,partner.password.update')->name('partner.password.update');

    Route::middleware(['auth', EnsurePartnerAccount::class])->prefix('portal')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('partner.dashboard');

        Route::get('/links', [LinksController::class, 'index'])->name('partner.links');
        Route::post('/links', [LinksController::class, 'store'])->middleware('throttle:30,1,partner.links.store')->name('partner.campaigns.store');
        Route::delete('/links/{campaign}', [LinksController::class, 'destroy'])->name('partner.campaigns.destroy');

        Route::get('/banners', [BannersController::class, 'index'])->name('partner.banners');
        Route::get('/promote', [PromoteController::class, 'index'])->name('partner.promote');
        Route::get('/guidelines', [PromoteController::class, 'guidelines'])->name('partner.guidelines');

        Route::get('/payouts', [PayoutsController::class, 'index'])->name('partner.payouts');
        Route::post('/payouts/method', [PayoutsController::class, 'saveMethod'])->middleware('throttle:10,1,partner.payouts.method')->name('partner.payouts.method');
        Route::post('/payouts/resolve-account', [PayoutsController::class, 'resolveAccount'])->middleware('throttle:15,1,partner.payouts.resolve')->name('partner.payouts.resolve');
        Route::post('/payouts/request', [PayoutsController::class, 'requestPayout'])->middleware('throttle:10,1,partner.payouts.request')->name('partner.payouts.request');

        Route::get('/news', [NewsController::class, 'index'])->name('partner.news');
        Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('partner.leaderboard');

        Route::get('/account', [AccountController::class, 'show'])->name('partner.account');
        Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('partner.account.profile');
        Route::put('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1,partner.account.password')->name('partner.account.password');
        Route::post('/account/two-factor', [AccountController::class, 'enableTwoFactor'])->middleware('throttle:10,1,partner.account.2fa')->name('partner.two-factor.enable');
        Route::post('/account/two-factor/confirm', [AccountController::class, 'confirmTwoFactor'])->middleware('throttle:10,1,partner.account.2fa')->name('partner.two-factor.confirm');
        Route::post('/account/two-factor/recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])->middleware('throttle:10,1,partner.account.2fa')->name('partner.two-factor.recovery-codes');
        Route::delete('/account/two-factor', [AccountController::class, 'disableTwoFactor'])->middleware('throttle:10,1,partner.account.2fa')->name('partner.two-factor.disable');
    });
});
