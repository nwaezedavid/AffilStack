<?php

namespace App\Http\Controllers;

use App\Models\CreditPackage;
use App\Models\PaymentTransaction;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\CustomerWallet\InsufficientCustomerWalletBalanceException;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    public function index(Request $request, CheckoutCountryResolver $countries, PaymentGatewayManager $gateways, CustomerWalletManager $wallets): View
    {
        $packages = CreditPackage::activePublicList();
        $isNigeria = $countries->isNigeria($request);
        $enabledGateways = $gateways->enabledForCountry($isNigeria ? 'NG' : 'US');
        $wallet = $wallets->walletFor($request->user());

        return view('dashboard.credit-topups.index', compact('packages', 'enabledGateways', 'isNigeria', 'wallet'));
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

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment, or contact support if it keeps happening.');
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

    /**
     * "Pay from wallet" for a credit pack — the wallet must be in the pack's
     * currency and cover its price. Debit and credit grant happen together
     * or not at all.
     */
    public function payWithWallet(CreditPackage $package, CustomerWalletManager $wallets, PaymentProcessor $processor): RedirectResponse
    {
        if (! $package->is_active) {
            return redirect()->route('credit-topups.index')->with('error', 'That package is no longer available.');
        }

        $user = auth()->user();
        $currency = strtoupper((string) $package->currency);

        if (! $wallets->canPay($user, $package->price_cents, $currency)) {
            return redirect()->route('credit-topups.index')->with('error', 'Your wallet balance doesn\'t cover this pack. Top up your wallet or pay by card.');
        }

        try {
            DB::transaction(function () use ($user, $package, $currency, $wallets, $processor) {
                $transaction = PaymentTransaction::create([
                    'user_id' => $user->id,
                    'credit_package_id' => $package->id,
                    'type' => 'credit_topup',
                    'gateway' => 'wallet',
                    'tx_ref' => 'affilstack_wallet_'.Str::uuid(),
                    'amount_cents' => $package->price_cents,
                    'currency' => $currency,
                    'status' => 'pending',
                ]);

                $wallets->debit($user, $package->price_cents, 'credit_pack', number_format($package->credits).' credits — '.$package->name, $transaction);

                $processed = $processor->process('wallet', [
                    'tx_ref' => $transaction->tx_ref,
                    'remote_id' => $transaction->tx_ref,
                    'status' => 'successful',
                    'amount' => $package->price_cents / 100,
                    'currency' => $currency,
                    'meta' => [],
                    'raw' => ['source' => 'customer_wallet'],
                ]);

                if ($processed?->status !== 'successful') {
                    throw new \RuntimeException('Wallet credit pack payment could not be completed.');
                }
            });
        } catch (InsufficientCustomerWalletBalanceException) {
            return redirect()->route('credit-topups.index')->with('error', 'Your wallet balance doesn\'t cover this pack. Top up your wallet or pay by card.');
        } catch (\RuntimeException $e) {
            report($e);

            return redirect()->route('credit-topups.index')->with('error', 'We could not take this payment from your wallet. Nothing was charged.');
        }

        return redirect()->route('billing.index')->with('success', number_format($package->credits).' credits have been added to your account.');
    }
}
