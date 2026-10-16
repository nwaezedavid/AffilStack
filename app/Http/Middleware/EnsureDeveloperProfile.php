<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Developer portal pages that need a developer profile (and one that
 * hasn't been suspended).
 */
class EnsureDeveloperProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        $developer = $request->user()?->developerProfile;

        if (! $developer) {
            return redirect()->route('developer.join');
        }

        if (! $developer->isActive()) {
            abort(403, 'Your developer account has been suspended. Contact AffilStack support.');
        }

        return $next($request);
    }
}
