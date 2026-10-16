<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The developer portal's sign-in check: guests go through "Sign in with
 * your AffilStack account" rather than the app's login page directly.
 */
class EnsureDeveloperAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            if ($request->isMethodSafe()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('developer.login');
        }

        return $next($request);
    }
}
