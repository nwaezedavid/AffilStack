<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated referral redirect — whoever clicks /r/{code} is a
 * prospective signup, not an AffilStack user yet. Logs the click, sets the
 * attribution cookie RegistrationController::store() reads later, and sends
 * them to pricing. An unrecognized code degrades to a plain, cookie-less
 * redirect to pricing rather than a 404 — a stale or mistyped referral link
 * should never look broken to the visitor.
 */
class ReferralController extends Controller
{
    public function redirect(string $code, Request $request, ReferralService $service): RedirectResponse
    {
        $referrer = User::where('referral_code', $code)->first();

        if (! $referrer) {
            return redirect()->route(config('referrals.fallback_route'));
        }

        $service->recordClick($referrer, $request);

        return redirect()
            ->route('registration.pricing')
            ->cookie($service->referralCookie($referrer));
    }
}
