<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The API usage prepay wallet's dashboard-side actions (see
 * ApiWalletManager for the ledger itself, MeterApiUsage for where a balance
 * actually gets spent). Rendered as a section of the existing API Access
 * page (ApiAccessController::index()) rather than its own nav item — same
 * reasoning that put outbound webhooks there too: this is a third facet of
 * the same "using the API" surface, not a separate destination.
 *
 * Owner-only, same as billing/subscription management elsewhere: a team
 * seat's own token still calls the metered API against the OWNER's wallet
 * (ApiWalletManager resolves billableUser() first), so only the owner
 * should be the one funding or configuring it.
 */
class ApiWalletController extends Controller
{
    /**
     * Mirrors CreditTopupController::checkout() closely — a one-time
     * purchase through PaymentGateway::initiateOneTimeCheckout(), resolved
     * later by the exact same billing.callback/webhook route and
     * PaymentProcessor::process(), just tagged 'api_wallet_topup' instead
     * of 'credit_topup' so it credits the wallet instead of credits_balance.
     */
    public function checkout(Request $request, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): RedirectResponse
    {
        abort_if($request->user()->isSeat(), 403);

        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:'.config('api_billing.min_topup_cents'), 'max:'.config('api_billing.max_topup_cents', 500000)],
            'gateway' => ['nullable', 'string'],
        ]);

        $enabledGateways = $gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US');

        if (empty($enabledGateways)) {
            return back()->with('error', 'Payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($enabledGateways)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $enabledGateways[0];
        $currency = $gateway->key() === 'paystack' ? 'USD' : (string) config('api_billing.currency', 'USD');

        try {
            $checkout = $gateway->initiateOneTimeCheckout(
                $request->user(),
                $validated['amount_cents'],
                $currency,
                'API wallet top-up',
                ['user_id' => $request->user()->id, 'type' => 'api_wallet_topup'],
            );
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start checkout right now. Please try again in a moment.');
        }

        PaymentTransaction::create([
            'user_id' => $request->user()->id,
            'type' => 'api_wallet_topup',
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $checkout['amount_cents'],
            // What the wallet gets, in the platform currency — amount_cents
            // is what the gateway charged (naira kobo on Paystack).
            'credited_amount_cents' => $validated['amount_cents'],
            'currency' => $checkout['currency'],
            'status' => 'pending',
        ]);

        return redirect()->away($checkout['link']);
    }

    /**
     * Auto-recharge configuration — the "bind your card for easy payments"
     * half of the feature. There's no separate "add a card" flow anywhere
     * in this app (see PaymentProcessor::grantApiWalletTopup()'s docblock):
     * a card is bound simply by paying with it once, so this only ever
     * picks among cards already on file via PaymentMethodRecorder.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSeat(), 403);

        $bounds = config('api_billing.auto_recharge');

        $validated = $request->validate([
            'auto_recharge_enabled' => ['sometimes', 'boolean'],
            'auto_recharge_threshold_cents' => ['nullable', 'integer', 'min:'.$bounds['min_threshold_cents'], 'max:'.$bounds['max_threshold_cents']],
            'auto_recharge_amount_cents' => ['nullable', 'integer', 'min:'.$bounds['min_amount_cents'], 'max:'.$bounds['max_amount_cents']],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('user_id', $request->user()->id)],
        ]);

        $user = $request->user();
        $enabled = $request->boolean('auto_recharge_enabled');
        $paymentMethodId = $validated['payment_method_id'] ?? $user->api_wallet_payment_method_id;

        if ($enabled && ! $paymentMethodId) {
            return back()->with('error', 'Pick a saved card for auto-recharge first — top up once with a card below if you don\'t have one saved yet.');
        }

        if ($enabled && (empty($validated['auto_recharge_threshold_cents']) && ! $user->api_wallet_auto_recharge_threshold_cents)) {
            return back()->withInput()->with('error', 'Set a balance threshold to trigger auto-recharge at.');
        }

        if ($enabled && (empty($validated['auto_recharge_amount_cents']) && ! $user->api_wallet_auto_recharge_amount_cents)) {
            return back()->withInput()->with('error', 'Set an amount to recharge each time.');
        }

        $user->update([
            'api_wallet_auto_recharge_enabled' => $enabled,
            'api_wallet_auto_recharge_threshold_cents' => $validated['auto_recharge_threshold_cents'] ?? $user->api_wallet_auto_recharge_threshold_cents,
            'api_wallet_auto_recharge_amount_cents' => $validated['auto_recharge_amount_cents'] ?? $user->api_wallet_auto_recharge_amount_cents,
            'api_wallet_payment_method_id' => $paymentMethodId,
        ]);

        return back()->with('success', $enabled ? 'Auto-recharge turned on — your wallet will top up automatically from now on.' : 'Auto-recharge settings saved.');
    }
}
