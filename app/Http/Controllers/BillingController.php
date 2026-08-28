<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Services\Flutterwave\FlutterwaveClient;
use App\Services\Flutterwave\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $subscription = auth()->user()->activeSubscription;

        return view('billing.index', compact('plans', 'subscription'));
    }

    public function checkout(Request $request, Plan $plan, FlutterwaveClient $flutterwave): RedirectResponse
    {
        $validated = $request->validate([
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $amount = $validated['billing_cycle'] === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents;

        try {
            $checkout = $flutterwave->initiateCheckout(auth()->user(), $plan, $validated['billing_cycle']);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        PaymentTransaction::create([
            'user_id' => auth()->id(),
            'type' => 'subscription',
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $amount,
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
            return redirect()->route('billing.index')->with('error', 'Payment was not completed.');
        }

        $verified = $flutterwave->verifyTransaction($transactionId);
        $transaction = $processor->process($verified);

        if ($transaction && $transaction->status === 'successful') {
            return redirect()->route('dashboard')->with('success', 'Your plan is now active. Welcome to AffiliStack.');
        }

        return redirect()->route('billing.index')->with('error', 'We could not verify that payment. No charge was applied to your plan.');
    }
}
