<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\PlanChangeService;
use App\Services\Payments\RefundEligibilityService;
use App\Services\Payments\RefundExecutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class BillingController extends Controller
{
    public function index(Request $request, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries, RefundEligibilityService $refundEligibility): View
    {
        // Audit item #7 (caching/performance) — see Plan::activePublicList().
        $plans = Plan::activePublicList();
        $subscription = auth()->user()->activeSubscription;
        $isNigeria = $countries->isNigeria($request);
        $enabledGateways = $gateways->enabledForCountry($isNigeria ? 'NG' : 'US');

        // Audit gap #2: PaymentTransaction has recorded every charge since
        // Phase 1, but this page never showed any of it — a user who
        // needed a receipt for expensing/taxes had to ask support to pull
        // it manually. Pending rows from an abandoned checkout are left
        // out; they're not something the user actually needs to see.
        $transactions = auth()->user()->transactions()
            ->where('status', '!=', 'pending')
            ->latest()
            ->paginate(10, pageName: 'transactions_page');

        // Audit item #2: saved payment methods, most-recently-used first —
        // see PaymentMethodRecorder for how these get created.
        $paymentMethods = auth()->user()->paymentMethods()->get();

        // Audit item #8: the refund button only ever shows up when the
        // strict, automatic policy already says yes — no "request a
        // refund and wait for a human" queue exists, so there's nothing to
        // show while ineligible except why.
        $refundableTransaction = $refundEligibility->eligibleTransaction(auth()->user());
        $refundHoursRemaining = $refundableTransaction ? $refundEligibility->hoursRemaining($refundableTransaction) : null;

        return view('billing.index', compact('plans', 'subscription', 'enabledGateways', 'transactions', 'isNigeria', 'paymentMethods', 'refundableTransaction', 'refundHoursRemaining'));
    }

    /**
     * Self-service plan switching (audit item #2). No active subscription
     * at all is just a normal full-price checkout — same as always. With
     * one, a downgrade (in monthly-equivalent price terms) is scheduled
     * for the end of the already-paid-for period and never touches a
     * gateway; an upgrade goes through checkout immediately but only for
     * the unused-time credit short of the new plan's price. See
     * PlanChangeService.
     */
    public function checkout(Request $request, Plan $plan, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries, PlanChangeService $planChanges): RedirectResponse
    {
        $validated = $request->validate([
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $subscription = auth()->user()->activeSubscription;

        if ($subscription
            && ($subscription->plan_id !== $plan->id || $subscription->billing_cycle !== $validated['billing_cycle'])
            && ! $planChanges->isUpgrade($subscription, $plan, $validated['billing_cycle'])
        ) {
            $planChanges->scheduleDowngrade($subscription, $plan, $validated['billing_cycle']);

            $when = $subscription->current_period_end?->format('M j, Y') ?? 'your next renewal';

            return redirect()->route('billing.index')->with('success', "You're scheduled to move to the {$plan->name} plan on {$when}. You'll keep your current plan's features until then.");
        }

        $enabledGateways = $gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US');

        if (empty($enabledGateways)) {
            return back()->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $enabledGateways[0];

        $overrideAmountCents = null;

        if ($subscription) {
            $fullPriceCents = $validated['billing_cycle'] === 'yearly' ? $plan->price_yearly_cents : $plan->price_monthly_cents;
            $credit = $planChanges->prorationCreditCents($subscription, $plan, $validated['billing_cycle']);
            $overrideAmountCents = max(0, $fullPriceCents - $credit);

            if ($overrideAmountCents <= 0) {
                $planChanges->applyImmediateUpgrade($subscription, $plan, $validated['billing_cycle']);

                PaymentTransaction::create([
                    'user_id' => auth()->id(),
                    'subscription_id' => $subscription->id,
                    'type' => 'subscription',
                    'gateway' => $subscription->gateway,
                    'tx_ref' => 'planchange_'.Str::uuid(),
                    'amount_cents' => 0,
                    'currency' => $plan->currency,
                    'status' => 'successful',
                    'processed_at' => now(),
                ]);

                return redirect()->route('billing.index')->with('success', 'Your plan is now active — fully covered by your existing plan credit.');
            }
        }

        try {
            $checkout = $gateway->initiateCheckout(auth()->user(), $plan, $validated['billing_cycle'], $overrideAmountCents);
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        PaymentTransaction::create([
            'user_id' => auth()->id(),
            'type' => 'subscription',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            // From the gateway itself, not assumed from the plan — see
            // PaymentGateway interface docblock.
            'amount_cents' => $checkout['amount_cents'],
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }

    /**
     * Backs out of a scheduled downgrade (audit item #2) — the current
     * plan simply keeps renewing as normal.
     */
    public function cancelScheduledChange(PlanChangeService $planChanges): RedirectResponse
    {
        $subscription = auth()->user()->activeSubscription;

        if ($subscription) {
            $planChanges->cancelScheduledChange($subscription);
        }

        return redirect()->route('billing.index')->with('success', 'Scheduled plan change canceled — you\'ll stay on your current plan.');
    }

    /**
     * Audit item #8's self-service refund button — fully automatic, no
     * admin in the loop. RefundExecutionService re-checks eligibility
     * itself (never trusts what the page rendered a moment ago), so this
     * action is just plumbing: run it, translate the outcome into a
     * flash message.
     */
    public function requestRefund(RefundExecutionService $refunds): RedirectResponse
    {
        try {
            $refund = $refunds->request(auth()->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('billing.index')->with('error', $e->getMessage());
        }

        if (! $refund->wasRefunded()) {
            return redirect()->route('billing.index')->with('error', 'We could not process your refund: '.$refund->reason);
        }

        return redirect()->route('billing.index')->with('success', 'Your payment has been refunded and your subscription canceled.');
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
