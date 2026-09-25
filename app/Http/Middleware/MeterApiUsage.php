<?php

namespace App\Http\Middleware;

use App\Services\ApiWallet\ApiWalletManager;
use App\Services\ApiWallet\InsufficientApiWalletBalanceException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bills a metered /v1/* route from the caller's API usage prepay wallet —
 * see config('api_billing') and ApiWalletManager. Runs after ApiTokenAuth
 * (needs $request->user() already resolved) but before throttle, so a call
 * that would be billed is billed before it's even attempted, not after
 * possibly being rate-limited — the reverse order would let a request 429
 * and still land here having already been charged.
 *
 * Charges optimistically the instant the request is accepted, exactly like
 * config('credits.costs') gates a dashboard generation at hasEnough()-check
 * time rather than waiting for a background job to finish — an API caller
 * is paying for API access to trigger the action, not for however long the
 * underlying work later takes to complete asynchronously. A non-2xx
 * response (validation failure, ownership check, the underlying feature's
 * own separate "not enough dashboard credits" 402, ...) refunds the charge:
 * the call didn't actually do the paid work, so it shouldn't burn wallet
 * balance for nothing.
 */
class MeterApiUsage
{
    public function __construct(protected ApiWalletManager $wallet) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();
        // Deliberately NOT config("api_billing.costs.{$routeName}", ...):
        // $routeName ('api.v1.offers.store') already contains dots, so
        // Laravel's dot-notation config lookup would try to traverse into
        // nested 'api' -> 'v1' -> ... keys that don't exist, rather than
        // matching config('api_billing.costs')'s single literal
        // 'api.v1.offers.store' key — silently resolving to the default
        // (0, unmetered) for every route. Fetching the costs array first
        // and indexing it directly with the exact route-name string avoids
        // that dot-splitting entirely.
        $costCents = (int) (config('api_billing.costs', [])[$routeName] ?? config('api_billing.default_cost_cents', 0));

        if ($costCents <= 0) {
            return $next($request);
        }

        // API roadmap item #5 (sandbox mode) — a sandbox token never spends
        // real money: OffersController::store() also skips the real
        // (paid) research pipeline entirely for one, so there is nothing
        // here to charge for in the first place.
        $token = $request->attributes->get('apiToken');
        if ($token?->is_sandbox) {
            return $next($request);
        }

        $user = $request->user();

        try {
            $this->wallet->charge($user, $costCents, "api:{$routeName}");
        } catch (InsufficientApiWalletBalanceException) {
            return response()->json([
                'message' => 'Insufficient API wallet balance for this request.',
                'wallet_balance_cents' => $this->wallet->balance($user),
                'required_cents' => $costCents,
                'top_up_url' => route('api-access.index'),
            ], 402);
        }

        // API roadmap item #7 (per-token usage analytics) — LogApiRequest
        // reads this back once the response is final, so it logs the NET
        // cost actually kept, not the gross charge above.
        $request->attributes->set('api_metered_cost_cents', $costCents);

        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            $this->wallet->refund($user, $costCents, "api:{$routeName}:request_failed");
            $request->attributes->set('api_metered_cost_cents', 0);
        }

        return $response;
    }
}
