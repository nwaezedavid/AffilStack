<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /api/v1/mobile/* is for the official AffilStack phone app only: these
 * endpoints act on the signed-in device itself (sign out, push token,
 * delete account), so a developer API token or a connected app's token
 * can't use them. Runs after api-token-auth.
 */
class EnsureMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->attributes->get('apiToken');

        if (! $token?->isMobileToken() || ! $request->attributes->get('mobileDevice')) {
            return response()->json(['message' => 'This endpoint is only available to the AffilStack app.'], 403);
        }

        return $next($request);
    }
}
