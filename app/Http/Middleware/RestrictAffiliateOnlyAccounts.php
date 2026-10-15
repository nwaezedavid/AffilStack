<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to the customer app's authenticated routes (see routes/web.php),
 * mirroring RestrictAgencySeats: a normal user passes straight through; an
 * affiliate-only partner account (User::isAffiliateOnly() — created by
 * AffiliateApplicationService::approve(), never a platform customer) is
 * sent to the Partner Portal, which is where everything they can do
 * lives. Only routes in config('referrals.affiliate_only_allowed_routes')
 * (signing out) still answer for them here.
 */
class RestrictAffiliateOnlyAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAffiliateOnly()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, config('referrals.affiliate_only_allowed_routes'), true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Partner accounts use the Partner Portal.');
        }

        return redirect()->route('partner.dashboard');
    }
}
