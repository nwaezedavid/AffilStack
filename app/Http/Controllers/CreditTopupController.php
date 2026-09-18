<?php

namespace App\Http\Controllers;

use App\Models\CreditPackage;
use App\Models\PaymentTransaction;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Users should be able to buy more credit tokens if their monthly
 * allocation finishes" — a one-time purchase on top of a plan's recurring
 * monthly grant. Mirrors BillingController::checkout()'s pattern closely
 * (create a pending PaymentTransaction, redirect to the gateway), but for a
 * CreditPackage instead of a Plan, and through
 * PaymentGateway::initiateOneTimeCheckout() instead of initiateCheckout().
 * The actual payment confirmation reuses billing.callback/
 * PaymentProcessor::process() entirely unchanged except for its
 * 'credit_topup' branch — see routes/web.php.
 */
class CreditTopupController extends Controller
{
    public function index(Request $request, CheckoutCountryResolver $countries, PaymentGatewayManager $gateways): View
    {
        $packages = CreditPackage::activePublicList();
        $isNigeria = $countries->isNigeria($request);
        $enabledGateways = $gateways->enabledForCountry($isNigeria ? 'NG' : 'US');

        return view('dashboard.credit-topups.index', compact('packages', 'enabledGateways', 'isNigeria'));
    }

    public function checkout(Request $request, CreditPackage $package, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): RedirectResponse
    {
        if (! $package->is_active) {
            return redirect()->route('credit-topups.index')->with('error', 'That package is no longer available.');
        }

        $enabledGateways = $gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US');

        if (empty($enabledGateways)) {
            return back()->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $enabledGateways[0];

        try {
            $checkout = $gateway->initiateOneTimeCheckout(
                auth()->user(),
                $package->price_cents,
                $package->currency,
                number_format($package->credits).' credits — '.$package->name,
                ['user_id' => auth()->id(), 'credit_package_id' => $package->id, 'type' => 'credit_topup'],
            );
        } catch (\RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        PaymentTransaction::create([
            'user_id' => auth()->id(),
            'credit_package_id' => $package->id,
            'type' => 'credit_topup',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $checkout['amount_cents'],
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }
}
