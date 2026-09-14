<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(PaymentGatewayManager $gateways): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $subscription = auth()->user()->activeSubscription;
        $enabledGateways = $gateways->enabled();

        return view('billing.index', compact('plans', 'subscription', 'enabledGateways'));
    }

    public function checkout(Request $request, Plan $plan, PaymentGatewayManager $gateways): RedirectResponse
    {
        $validated = $request->validate([
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $enabledGateways = $gateways->enabled();

        if (empty($enabledGateways)) {
            return back()->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $enabledGateways[0];
        $amount = $validated['billing_cycle'] === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents;

        try {
            $checkout = $gateway->initiateCheckout(auth()->user(), $plan, $validated['billing_cycle']);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        PaymentTransaction::create([
            'user_id' => auth()->id(),
            'type' => 'subscription',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $amount,
            'currency' => $plan->currency,
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
            return redirect()->route('billing.index')->with('error', 'Payment was not completed.');
        }

        $transaction = $processor->process($gatewayKey, $result);

        if ($transaction && $transaction->status === 'successful') {
            return redirect()->route('dashboard')->with('success', 'Your plan is now active. Welcome to AffilStack.');
        }

        return redirect()->route('billing.index')->with('error', 'We could not verify that payment. No charge was applied to your plan.');
    }
}
