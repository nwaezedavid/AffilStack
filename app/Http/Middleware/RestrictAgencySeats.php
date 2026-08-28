<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every authenticated route (see routes/web.php). A normal user
 * or the account owner passes straight through; a team seat (item 10) may
 * only hit routes named in config('agency.seat_allowed_routes') — the
 * coarse "which pages exist for a seat at all" gate. The finer "is this
 * specifically YOUR assigned offer" check still happens per-resource in
 * each controller via Offer::isAccessibleBy() / Generation::isAccessibleBy().
 */
class RestrictAgencySeats
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSeat()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        abort_unless(
            $routeName && in_array($routeName, config('agency.seat_allowed_routes'), true),
            403,
            'This account is a team seat and only has access to its assigned offer.',
        );

        return $next($request);
    }
}
