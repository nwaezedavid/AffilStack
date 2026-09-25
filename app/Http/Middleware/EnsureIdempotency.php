<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API roadmap item #1 — an opt-in Idempotency-Key header on POST /offers
 * and POST /crm-contacts (see routes/api.php) lets a client safely retry a
 * request that timed out without risking a second offer/contact. No
 * header at all means this middleware is a complete no-op, so every
 * existing caller and test is unaffected.
 *
 * Runs BEFORE meter-api-usage on api.v1.offers.store (see the explicit
 * ordering on that route) so a replayed request short-circuits before it
 * could ever be billed again — the cached response it returns already
 * reflects whatever the first attempt was charged.
 *
 * Only a 2xx response is ever cached. A failed first attempt (validation
 * error, insufficient wallet/credits) is deliberately left retryable under
 * the same key: MeterApiUsage already refunded that attempt, so nothing
 * about it is worth freezing in place, and the caller may well succeed on
 * the very next try (e.g. after topping up).
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        if (mb_strlen($key) > 255) {
            return response()->json(['message' => 'Idempotency-Key must be 255 characters or fewer.'], 400);
        }

        $user = $request->user();
        $routeName = (string) $request->route()?->getName();
        $hash = hash('sha256', $request->getContent());

        $existing = IdempotencyKey::where('user_id', $user->id)->where('key', $key)->first();

        if ($existing) {
            if ($existing->request_hash !== $hash) {
                return response()->json([
                    'message' => "Idempotency-Key \"{$key}\" was already used for a different request.",
                ], 422);
            }

            return response()->json($existing->response_body, $existing->response_status);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            try {
                IdempotencyKey::create([
                    'user_id' => $user->id,
                    'key' => $key,
                    'route' => $routeName,
                    'request_hash' => $hash,
                    'response_status' => $response->getStatusCode(),
                    // Decoded, not the raw JSON string: response_body casts
                    // to 'array' on the model, and that cast already
                    // json_encodes whatever it's given — passing an
                    // already-encoded string here would double-encode it.
                    'response_body' => json_decode($response->getContent(), true) ?? [],
                ]);
            } catch (QueryException $e) {
                // Unique (user_id, key) race: a concurrent identical request
                // won and stored it first. Not this request's problem to
                // solve — the caller already has a good response either way.
                if (! str_contains($e->getMessage(), 'idempotency_keys')) {
                    throw $e;
                }
            }
        }

        return $response;
    }
}
