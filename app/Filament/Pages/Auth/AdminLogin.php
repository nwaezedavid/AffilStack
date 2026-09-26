<?php

namespace App\Filament\Pages\Auth;

use App\Http\Middleware\EnsurePanelLoginCompleted;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;

/**
 * Filament's own login, plus a marker that this session really went through
 * it — password AND the TOTP challenge. The panel shares the `web` guard
 * with the customer login (/login) and "Continue with Google", neither of
 * which knows about the panel's two-factor requirement, so without this
 * marker an admin's password alone (or their Google account) opened the
 * whole panel. See EnsurePanelLoginCompleted.
 */
class AdminLogin extends Login
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null && Filament::auth()->check()) {
            session()->put(EnsurePanelLoginCompleted::SESSION_KEY, Filament::auth()->id());
        }

        return $response;
    }
}
