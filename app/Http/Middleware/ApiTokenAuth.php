<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates every routes/api.php request — originally just the browser
 * capture extension (item 11), now also the general-purpose /v1/* API —
 * via "Authorization: Bearer <token>" — see ApiToken for how the token
 * itself is generated and hashed. Deliberately separate from the
 * dashboard's session-based auth: an API client runs outside the app's own
 * origin, so a bearer token is the standard approach (the same one
 * Sanctum's own personal-access tokens use), not a session cookie or CSRF
 * token.
 *
 * Stashes the resolved ApiToken itself on the request (not just its user)
 * so a downstream middleware — see EnsureAdminApiToken — can check the
 * token's own $type without a second lookup.
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

        $user = $token->user;

        // A soft-deleted owner resolves to null — reject cleanly instead of
        // failing somewhere downstream with a 500.
        if (! $user) {
            return response()->json(['message' => 'Invalid or revoked token.'], 401);
        }

        // Suspension is the platform's main abuse control; the dashboard
        // enforces it (EnsureAccountNotSuspended) but tokens bypassed it.
        // A seat is also blocked when the account paying for it is.
        if ($user->is_suspended || $user->billableUser()->is_suspended) {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }

        $token->update(['last_used_at' => now()]);

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('apiToken', $token);

        return $next($request);
    }
}
