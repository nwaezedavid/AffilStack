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
 *
 * A handful of additional routes (config('agency.shared_only_routes')) only
 * make sense for a "shared" (Business tier) seat — browsing or adding to
 * the whole team's offer list. An "isolated"-plan seat (the original agency
 * model) never reaches them, since it's scoped to exactly one offer.
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

        if ($routeName && in_array($routeName, config('agency.shared_only_routes'), true)) {
            abort_unless($user->hasSharedTeamAccess(), 403, 'This account is scoped to a single offer.');

            return $next($request);
        }

        abort_unless(
            $routeName && in_array($routeName, config('agency.seat_allowed_routes'), true),
            403,
            'This account is a team seat and only has access to its assigned offer.',
        );

        return $next($request);
    }
}
