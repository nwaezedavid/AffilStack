<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to the same authenticated route group as RestrictAgencySeats (see
 * routes/web.php), which it deliberately mirrors: a normal user passes
 * straight through; an affiliate-only account (User::isAffiliateOnly() —
 * created by AffiliateApplicationService::approve(), never a platform
 * customer) may only hit routes named in
 * config('referrals.affiliate_only_allowed_routes') — referrals, profile,
 * and logout, nothing content- or billing-related.
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

        abort_unless(
            $routeName && in_array($routeName, config('referrals.affiliate_only_allowed_routes'), true),
            403,
            'This account is an affiliate-only account and only has access to the Referrals page.',
        );

        return $next($request);
    }
}
