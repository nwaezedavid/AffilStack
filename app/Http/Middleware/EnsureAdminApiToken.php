<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates /api/v1/admin/* — must run after 'api-token-auth' (which resolves
 * the token and stashes it on the request; see ApiTokenAuth). Requires
 * BOTH the token itself to be type='admin' (only mintable by a full admin,
 * from the Filament API Tokens resource) AND the token's owning user to
 * still be a full admin right now — a demoted admin's old admin token
 * stops working immediately rather than staying valid until revoked.
 */
class EnsureAdminApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('apiToken');

        if (! $token?->isAdminToken() || ! $request->user()?->isFullAdmin()) {
            return response()->json(['message' => 'This token is not authorized for the admin API.'], 403);
        }

        return $next($request);
    }
}
