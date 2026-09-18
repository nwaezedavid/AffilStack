<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\AffiliateApplication;
use App\Services\Referrals\AffiliateApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The one-time "set your password" link an approved affiliate applicant
 * gets by email — this app's account creation always defers a real
 * password to a step like this one (see PendingSignup's checkout flow),
 * but there's no forgot-password/reset system anywhere else to reuse for
 * it, so it's self-contained: see
 * AffiliateApplication::hasValidSetPasswordToken() for the token check and
 * AffiliateApplicationService::approve()/setPassword() for the rest of the
 * lifecycle.
 */
class AffiliateSetPasswordController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, AffiliateApplication $application): View|RedirectResponse
    {
        if (! $application->hasValidSetPasswordToken($request->query('token'))) {
            return redirect()->route('affiliate.landing')->with('error', 'This link is invalid or has expired.');
        }

        return view('affiliate.set-password', ['application' => $application, 'token' => $request->query('token')]);
    }

    public function store(Request $request, AffiliateApplication $application, AffiliateApplicationService $applications): RedirectResponse
    {
        if (! $application->hasValidSetPasswordToken($request->input('token'))) {
            return redirect()->route('affiliate.landing')->with('error', 'This link is invalid or has expired.');
        }

        $validated = $request->validate(['password' => $this->passwordRules()]);

        try {
            $applications->setPassword($application, $validated['password']);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('affiliate.landing')->with('error', $e->getMessage());
        }

        Auth::login($application->approvedUser->fresh());

        return redirect()->route('referrals.index')->with('success', 'Password set — welcome to the affiliate program!');
    }
}
