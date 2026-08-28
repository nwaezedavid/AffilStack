<?php

use App\Http\Controllers\Api\ExtensionApiController;
use Illuminate\Support\Facades\Route;

// The browser capture extension's entire backend surface (item 11).
// Bearer-token authenticated — see App\Http\Middleware\ApiTokenAuth — never
// session/CSRF based, since an extension runs in its own
// chrome-extension:// origin.
Route::middleware('api-token-auth')->group(function () {
    Route::get('/me', [ExtensionApiController::class, 'me'])->name('api.me');
    Route::get('/offers', [ExtensionApiController::class, 'offers'])->name('api.offers');
    Route::post('/clips', [ExtensionApiController::class, 'storeClip'])->name('api.clips.store');
});
