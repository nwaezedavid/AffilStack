<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API roadmap item #2 — rejects any non-read HTTP verb when the resolved
 * token (see ApiTokenAuth, which stashes it on the request) is scoped
 * 'read_only'. Applied to the whole /v1 group so a read-only token can
 * still reach every existing GET endpoint but never POST/PATCH/DELETE —
 * including the metered POST /offers, so this runs before meter-api-usage
 * in the group and never risks a charge for a request it's about to block.
 */
class EnsureTokenScope
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('apiToken');

        if ($token?->isReadOnly() && ! $request->isMethodSafe()) {
            return response()->json([
                'message' => 'This token is read-only and cannot make write requests.',
            ], 403);
        }

        return $next($request);
    }
}
