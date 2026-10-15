<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Partner Portal is for approved affiliate-only partner accounts. A
 * customer (who shares a session with the portal only when everything
 * runs on one host) is sent to their own Refer & Earn page; a suspended
 * partner is signed out.
 */
class EnsurePartnerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('partner.login');
        }

        if (! $user->isAffiliateOnly()) {
            return redirect()->route('referrals.index')
                ->with('success', 'The Partner Portal is for approved partners. Your own referral link and earnings are right here.');
        }

        if ($user->is_suspended) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('partner.login')
                ->with('error', 'Your partner account has been suspended. Contact support if you believe this is a mistake.');
        }

        return $next($request);
    }
}
