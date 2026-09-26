<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $validated = $request->validate([
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            // Audit item #8 — the Google button skips the main signup form
            // entirely, so this checkbox lives on its own mini-form instead.
            // See startGoogleSignup() for where the timestamp is used.
            'accepts_refund_policy' => ['accepted'],
        ], [
            'accepts_refund_policy.accepted' => 'You must accept the Refund & Cancellation Policy to continue.',
        ]);

        if (! $plan->is_active) {
            return back()->with('error', 'That plan is no longer available.');
        }

        return $this->redirectToGoogle($request, $google, [
            'type' => 'signup',
            'plan_id' => $plan->id,
            'billing_cycle' => $validated['billing_cycle'],
            'refund_policy_accepted_at' => now()->toIso8601String(),
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
            $message = 'Signed in with Google.';

            if (! $user->google_id) {
                // First Google sign-in for an account created with a
                // password. Signup never proves ownership of the email
                // (anyone can pay for a plan under someone else's address),
                // so whoever set that password may not be this Google
                // account's owner. Google has now proven ownership — retire
                // the old password and sign out every other session, so a
                // pre-registered account can't be shared with an intruder.
                $user->forceFill([
                    'google_id' => $profile['sub'],
                    'password' => Hash::make(Str::random(64)),
                    'remember_token' => Str::random(60),
                ])->save();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }

                $message = 'Signed in with Google. For your security your previous password was retired — keep using Google, or use "Forgot password" to set a new one.';
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('dashboard')->with('success', $message);
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

        // A deactivated account (30-day grace period) still owns this email —
        // taking payment for a new one would fail on the unique email after
        // the customer had already paid.
        if (User::withTrashed()->where('email', $profile['email'])->exists()) {
            return redirect()->route('login')->with('error', 'This email belongs to a deactivated account — contact support to restore it.');
        }

        $billingCycle = $intent['billing_cycle'] ?? 'monthly';

        // Same country-aware choice as the password signup form — a
        // Nigerian card can't be sent to a USD-only gateway and vice versa.
        $enabledGateways = $gateways->enabledForCountry(app(CheckoutCountryResolver::class)->isNigeria($request) ? 'NG' : 'US');

        if (empty($enabledGateways)) {
            return redirect()->route('registration.form', $plan)->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === ($intent['gateway'] ?? null)) ?? $enabledGateways[0];

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
            'refund_policy_accepted_at' => $intent['refund_policy_accepted_at'] ?? now(),
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

        $request->session()->put(RegistrationController::SESSION_PENDING_SIGNUP_KEY, $pending->id);

        PaymentTransaction::create([
            'pending_signup_id' => $pending->id,
            'type' => 'signup',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            // What the gateway will actually charge — Paystack converts to
            // naira, so the plan's USD price here made every Paystack
            // Google signup fail verification after the customer had paid.
            'amount_cents' => $checkout['amount_cents'],
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }
}
