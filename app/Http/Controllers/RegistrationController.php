<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Plan;
use App\Models\User;
use App\Services\Flutterwave\FlutterwaveClient;
use App\Services\Flutterwave\PaymentProcessor;
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

    public function pricing(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('registration.pricing', compact('plans'));
    }

    public function showForm(Plan $plan): View
    {
        return view('registration.signup', compact('plan'));
    }

    public function store(Request $request, Plan $plan, FlutterwaveClient $flutterwave, ReferralService $referrals): RedirectResponse
    {
        if (! $plan->is_active) {
            return back()->with('error', 'That plan is no longer available.');
        }

        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ])->validate();

        if (User::where('email', $validated['email'])->exists()) {
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
        ]);

        $referrals->attachReferrerToPendingSignup($pending, $request);

        try {
            $checkout = $flutterwave->initiateSignupCheckout(
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

        PaymentTransaction::create([
            'pending_signup_id' => $pending->id,
            'type' => 'signup',
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $validated['billing_cycle'] === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents,
            'currency' => $plan->currency,
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }

    public function callback(Request $request, FlutterwaveClient $flutterwave, PaymentProcessor $processor): RedirectResponse
    {
        $txRef = $request->query('tx_ref');
        $transactionId = $request->query('transaction_id');
        $status = $request->query('status');

        if (! $txRef || ! $transactionId || $status !== 'successful') {
            return redirect()->route('registration.pricing')->with('error', 'Payment was not completed, so no account was created.');
        }

        $verified = $flutterwave->verifyTransaction($transactionId);
        $transaction = $processor->process($verified);

        if ($transaction && $transaction->status === 'successful' && $transaction->user_id) {
            Auth::login($transaction->user);

            return redirect()->route('dashboard')->with('success', 'Payment received — welcome to AffilStack.');
        }

        return redirect()->route('registration.pricing')->with('error', 'We could not verify that payment. No account was created.');
    }
}
