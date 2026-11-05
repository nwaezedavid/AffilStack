<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CustomerWallet;
use App\Models\PaymentTransaction;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The customer wallet page: balance, top-up and history, plus the "Use
 * wallet for renewals" switch. Top-ups go through the normal one-time
 * checkout and are credited by PaymentProcessor only once the payment is
 * verified (webhook or callback). There are no withdrawals.
 *
 * Owner-only like billing: a team seat spends its owner's wallet but can't
 * fund or configure it.
 */
class WalletController extends Controller
{
    public function index(Request $request, CustomerWalletManager $wallets, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View
    {
        $wallet = $wallets->walletFor($request->user());
        $transactions = $wallet->transactions()->paginate(15);
        $isNigeria = $countries->isNigeria($request);
        $enabledGateways = $this->topUpGateways($gateways->enabledForCountry($isNigeria ? 'NG' : 'US'), $wallet);

        return view('wallet.index', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'enabledGateways' => $enabledGateways,
            'isNigeria' => $isNigeria,
            'minTopup' => (int) config('customer_wallet.min_topup_minor'),
            'maxTopup' => (int) config('customer_wallet.max_topup_minor'),
        ]);
    }

    public function topUp(Request $request, CustomerWalletManager $wallets, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): RedirectResponse
    {
        abort_if($request->user()->isSeat(), 403);

        $min = (int) config('customer_wallet.min_topup_minor') / 100;
        $max = (int) config('customer_wallet.max_topup_minor') / 100;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$min, 'max:'.$max, 'decimal:0,2'],
            'gateway' => ['nullable', 'string'],
        ]);

        $wallet = $wallets->walletFor($request->user());
        $amountMinor = (int) round(((float) $validated['amount']) * 100);
        $enabledGateways = $this->topUpGateways($gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US'), $wallet);

        if (empty($enabledGateways)) {
            return back()->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $enabledGateways[0];

        try {
            $checkout = $gateway->initiateOneTimeCheckout(
                $request->user(),
                $amountMinor,
                $wallet->currency,
                'Wallet top-up',
                ['user_id' => $request->user()->id, 'type' => 'customer_wallet_topup'],
            );
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment, or contact support if it keeps happening.');
        }

        PaymentTransaction::create([
            'user_id' => $request->user()->id,
            'type' => 'customer_wallet_topup',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $checkout['amount_cents'],
            // What the wallet gets, in the wallet's currency — amount_cents
            // is what the gateway charges (naira kobo on Paystack).
            'credited_amount_cents' => $amountMinor,
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }

    public function updateSettings(Request $request, CustomerWalletManager $wallets): RedirectResponse
    {
        abort_if($request->user()->isSeat(), 403);

        $enabled = $request->boolean('use_for_renewals');
        $wallets->walletFor($request->user())->update(['use_for_renewals' => $enabled]);

        return back()->with('success', $enabled
            ? 'Your wallet will be used first for plan renewals.'
            : 'Your wallet won\'t be used for renewals — your saved card will be charged instead.');
    }

    /**
     * Paystack converts the amount it's given from USD to naira, so it can
     * only top up a USD wallet.
     *
     * @param  array<int, PaymentGateway>  $enabledGateways
     * @return array<int, PaymentGateway>
     */
    protected function topUpGateways(array $enabledGateways, CustomerWallet $wallet): array
    {
        return array_values(array_filter($enabledGateways, fn ($gateway) => $gateway->key() !== 'paystack' || $wallet->currency === 'USD'));
    }
}
