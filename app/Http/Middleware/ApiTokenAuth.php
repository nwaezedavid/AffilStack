<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates routes/api.php requests from the browser capture extension
 * (item 11) via "Authorization: Bearer <token>" — see ApiToken for how the
 * token itself is generated and hashed. Deliberately separate from the
 * dashboard's session-based auth: an extension runs in its own
 * chrome-extension:// origin, so a bearer token is the standard approach
 * (the same one Sanctum's own personal-access tokens use), not a session
 * cookie or CSRF token.
 */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $request->bearerToken();

        if (! $plainText) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        $token = ApiToken::findByPlainText($plainText);

        if (! $token) {
            return response()->json(['message' => 'Invalid or revoked token.'], 401);
        }

        $token->update(['last_used_at' => now()]);

        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
