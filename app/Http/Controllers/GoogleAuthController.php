<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * "Continue with Google" — one callback resolves to the same outcome
 * regardless of which button sent the visitor here: find-or-create the
 * account for a Google-verified email address. The only branch is what
 * happens when no account exists yet:
 *  - came from the signup form (a plan is stashed in session) → proceed
 *    into the normal paid-signup checkout flow, exactly like
 *    RegistrationController::store, just without a password step. No
 *    account is created until payment verifies — Google sign-in does not
 *    bypass the no-free-registration rule.
 *  - came from the login page (no plan stashed) → there's nothing to log
 *    into, so send them to pick a plan instead.
 * An existing account match (by google_id or, failing that, by verified
 * email) always just logs the visitor in and links google_id if it wasn't
 * already — Google having verified the email is treated as sufficient
 * proof to link, the same trust basis email/password reset already relies
 * on.
 */
class GoogleAuthController extends Controller
{
    protected const SESSION_STATE_KEY = 'google_oauth_state';

    protected const SESSION_INTENT_KEY = 'google_oauth_intent';

    public function redirectForLogin(Request $request, GoogleOAuthService $google): RedirectResponse
    {
        return $this->redirectToGoogle($request, $google, ['type' => 'login']);
    }

    public function redirectForSignup(Request $request, Plan $plan, GoogleOAuthService $google): RedirectResponse
    {
        $validated = $request->validate(['billing_cycle' => ['required', 'in:monthly,yearly']]);

        if (! $plan->is_active) {
            return back()->with('error', 'That plan is no longer available.');
        }

        return $this->redirectToGoogle($request, $google, [
            'type' => 'signup',
            'plan_id' => $plan->id,
            'billing_cycle' => $validated['billing_cycle'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    protected function redirectToGoogle(Request $request, GoogleOAuthService $google, array $intent): RedirectResponse
    {
        if (! $google->isEnabled()) {
            return back()->with('error', 'Google sign-in is not available right now.');
        }

        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);
        $request->session()->put(self::SESSION_INTENT_KEY, $intent);

        return redirect()->away($google->authorizationUrl(route('google.callback'), $state));
    }

    public function callback(
        Request $request,
        GoogleOAuthService $google,
        PaymentGatewayManager $gateways,
        ReferralService $referrals
    ): RedirectResponse {
        $state = $request->session()->pull(self::SESSION_STATE_KEY);
        $intent = $request->session()->pull(self::SESSION_INTENT_KEY, ['type' => 'login']);

        if (! $state || $request->query('state') !== $state || ! $request->query('code')) {
            return redirect()->route('login')->with('error', 'Google sign-in failed — please try again.');
        }

        try {
            $profile = $google->resolveProfile((string) $request->query('code'), route('google.callback'));
        } catch (\RuntimeException $e) {
            report($e);

            return redirect()->route('login')->with('error', 'We could not complete Google sign-in. Please try again.');
        }

        if (! $profile['email_verified'] || ! $profile['email']) {
            return redirect()->route('login')->with('error', 'That Google account\'s email address is not verified.');
        }

        $user = User::where('google_id', $profile['sub'])->first()
            ?? User::where('email', $profile['email'])->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $profile['sub']]);
            }

            Auth::login($user);

            return redirect()->route('dashboard')->with('success', 'Signed in with Google.');
        }

        if (($intent['type'] ?? null) !== 'signup') {
            return redirect()->route('registration.pricing')
                ->with('error', "No AffilStack account found for {$profile['email']} — pick a plan to get started.");
        }

        return $this->startGoogleSignup($profile, $intent, $gateways, $referrals, $request);
    }

    /**
     * @param  array{sub: string, email: string, name: string}  $profile
     * @param  array<string, mixed>  $intent
     */
    protected function startGoogleSignup(array $profile, array $intent, PaymentGatewayManager $gateways, ReferralService $referrals, Request $request): RedirectResponse
    {
        $plan = Plan::find($intent['plan_id'] ?? null);

        if (! $plan || ! $plan->is_active) {
            return redirect()->route('registration.pricing')->with('error', 'That plan is no longer available.');
        }

        $billingCycle = $intent['billing_cycle'] ?? 'monthly';

        $enabledGateways = $gateways->enabled();

        if (empty($enabledGateways)) {
            return redirect()->route('registration.form', $plan)->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = $enabledGateways[0];

        // A previous abandoned checkout with this email shouldn't block a
        // retry — same rule as the password signup flow.
        PendingSignup::where('email', $profile['email'])->where('status', 'pending')->delete();

        $pending = PendingSignup::create([
            'name' => $profile['name'],
            'email' => $profile['email'],
            'google_id' => $profile['sub'],
            // Never used to log in — Google is this account's only way in —
            // but the column is NOT NULL, so a random value fills it.
            'password' => Hash::make(Str::random(40)),
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'tx_ref' => 'pending_'.Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $referrals->attachReferrerToPendingSignup($pending, $request);

        try {
            $checkout = $gateway->initiateSignupCheckout($pending->email, $pending->name, $plan, $billingCycle, $pending->id);
        } catch (\RuntimeException $e) {
            report($e);
            $pending->delete();

            return redirect()->route('registration.form', $plan)->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        $pending->update(['tx_ref' => $checkout['tx_ref']]);

        PaymentTransaction::create([
            'pending_signup_id' => $pending->id,
            'type' => 'signup',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $billingCycle === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }
}
