<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel sessions must come from the panel's own login page, which is
 * the only one that enforces the admin two-factor challenge (see
 * App\Filament\Pages\Auth\AdminLogin). A session authenticated any other
 * way — the customer /login form, Google sign-in, or a remember-me cookie —
 * is signed out and sent to the panel login.
 */
class EnsurePanelLoginCompleted
{
    public const SESSION_KEY = 'admin_panel_login_completed_for';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Filament::auth();

        if ($guard->check() && (int) $request->session()->get(self::SESSION_KEY) !== (int) $guard->id()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getLoginUrl());
        }

        return $next($request);
    }
}
