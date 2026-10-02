<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agent 04: the analytics/consent beacons are CSRF-exempt (a cached page
 * can't guarantee a fresh token), so instead they refuse any request a
 * browser marks as coming from another site — via the Origin header, or
 * Sec-Fetch-Site when Origin is absent. Stops a third-party page from
 * forging a consent record or events for a signed-in visitor. Requests
 * with neither header (old browsers, server-side tests) are allowed: the
 * consent cookie check in the tracker still applies to them.
 */
class RejectCrossSiteBeacon
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && $origin !== 'null') {
            $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));

            if ($originHost !== strtolower($request->getHost())) {
                return response()->json(['ok' => false], 403);
            }
        }

        if ($request->headers->get('Sec-Fetch-Site') === 'cross-site') {
            return response()->json(['ok' => false], 403);
        }

        return $next($request);
    }
}
