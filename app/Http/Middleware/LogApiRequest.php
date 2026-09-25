<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API roadmap item #7 (per-token usage analytics) — records every /v1/*
 * call that reaches a resolved token, not just the metered ones, so the
 * API Access page can show call volume per token even where every call is
 * free. Applied at the route-group level, outside meter-api-usage, so by
 * the time this runs on the way back out MeterApiUsage has already decided
 * whether to keep or refund its charge — see the "api_metered_cost_cents"
 * request attribute it sets, which is the NET cost logged here, never the
 * gross charge.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var ApiToken|null $token */
        $token = $request->attributes->get('apiToken');

        if ($token) {
            ApiRequestLog::create([
                'api_token_id' => $token->id,
                'user_id' => $token->user_id,
                'route' => (string) $request->route()?->getName(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'cost_cents' => (int) $request->attributes->get('api_metered_cost_cents', 0),
            ]);
        }

        return $response;
    }
}
