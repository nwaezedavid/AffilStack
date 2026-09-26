<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use App\Services\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The only door into an AffilStack account. There is no free signup —
 * per product decision, a user picks a plan, pays through Flutterwave, and
 * the account is created the instant that payment verifies. See
 * PaymentProcessor::completeSignup() for the other half of this flow.
 */
class RegistrationController extends Controller
{
    use PasswordValidationRules;

    public const SESSION_PENDING_SIGNUP_KEY = 'signup.pending_signup_id';

    public function pricing(): View
    {
        // Audit item #7 (caching/performance) — see Plan::activePublicList().
        $plans = Plan::activePublicList();

        return view('registration.pricing', compact('plans'));
    }

    public function showForm(Request $request, Plan $plan, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View
    {
        $isNigeria = $countries->isNigeria($request);
        $enabledGateways = $gateways->enabledForCountry($isNigeria ? 'NG' : 'US');

        return view('registration.signup', compact('plan', 'enabledGateways', 'isNigeria'));
    }

    public function store(Request $request, Plan $plan, PaymentGatewayManager $gateways, ReferralService $referrals, CheckoutCountryResolver $countries): RedirectResponse
    {
        if (! $plan->is_active) {
            return back()->with('error', 'That plan is no longer available.');
        }

        $enabledGateways = $gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US');

        if (empty($enabledGateways)) {
            return back()->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $enabledGateways[0];

        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            // Audit item #8 — required, not just recommended: no account is
            // ever created without this being accepted first. See
            // PaymentProcessor::completeSignup() for where it's copied onto
            // the User row once payment verifies.
            'accepts_refund_policy' => ['accepted'],
        ], [
            'accepts_refund_policy.accepted' => 'You must accept the Refund & Cancellation Policy to continue.',
        ])->validate();

        // withTrashed: a deactivated account (30-day grace period) still owns
        // its email — letting a new signup pay for it would end in a unique-
        // key failure after the money was already taken.
        if (User::withTrashed()->where('email', $validated['email'])->exists()) {
            return back()->withInput()->withErrors([
                'email' => 'An account with this email already exists. Try signing in instead.',
            ]);
        }

        // A previous abandoned checkout with this email shouldn't block a
        // retry — replace it rather than erroring on a stale unique row.
        PendingSignup::where('email', $validated['email'])->where('status', 'pending')->delete();

        $pending = PendingSignup::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'plan_id' => $plan->id,
            'billing_cycle' => $validated['billing_cycle'],
            'tx_ref' => 'pending_'.Str::uuid(), // placeholder, replaced below once we have the real tx_ref
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
            'refund_policy_accepted_at' => now(),
        ]);

        $referrals->attachReferrerToPendingSignup($pending, $request);

        try {
            $checkout = $gateway->initiateSignupCheckout(
                $validated['email'],
                $validated['name'],
                $plan,
                $validated['billing_cycle'],
                $pending->id
            );
        } catch (\RuntimeException $e) {
            report($e);
            $pending->delete();

            return back()->withInput()->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        $pending->update(['tx_ref' => $checkout['tx_ref']]);

        // Only the browser that started this signup gets logged in by the
        // callback — see callback().
        $request->session()->put(self::SESSION_PENDING_SIGNUP_KEY, $pending->id);

        PaymentTransaction::create([
            'pending_signup_id' => $pending->id,
            'type' => 'signup',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'plan_id' => $plan->id,
            'billing_cycle' => $validated['billing_cycle'],
            // From the gateway itself, not assumed from the plan — a
            // gateway that settles in a different currency (Paystack/NGN)
            // reports the converted amount here. See PaymentGateway interface.
            'amount_cents' => $checkout['amount_cents'],
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }

    public function callback(Request $request, PaymentGatewayManager $gateways, PaymentProcessor $processor): RedirectResponse
    {
        $gatewayKey = (string) $request->query('gateway', 'flutterwave');
        $gateway = $gateways->get($gatewayKey);
        $result = $gateway->resolveFromCallback($request);

        if (! $result) {
            return redirect()->route('registration.pricing')->with('error', 'Payment was not completed, so no account was created.');
        }

        $transaction = $processor->process($gatewayKey, $result);

        if ($transaction && $transaction->status === 'successful' && $transaction->user_id) {
            // This URL is replayable (browser history, logs, a guessable
            // Flutterwave transaction id), so it must never act as a login
            // link on its own: only the session that started this exact
            // signup is signed in. Anyone else — including the real
            // customer finishing on another device — signs in normally.
            $startedHere = $transaction->pending_signup_id
                && (int) $request->session()->pull(self::SESSION_PENDING_SIGNUP_KEY) === (int) $transaction->pending_signup_id;

            if ($startedHere && $transaction->user && ! $transaction->user->is_suspended) {
                Auth::login($transaction->user);
                $request->session()->regenerate();

                return redirect()->route('dashboard')->with('success', 'Payment received — welcome to AffilStack.');
            }

            return redirect()->route('login')->with('success', 'Payment received — your account is ready. Sign in to get started.');
        }

        return redirect()->route('registration.pricing')->with('error', 'We could not verify that payment. No account was created.');
    }
}
