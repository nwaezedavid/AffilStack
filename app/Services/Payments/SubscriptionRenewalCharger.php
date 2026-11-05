<?php

namespace App\Services\Payments;

use App\Models\CustomerWallet;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\CustomerWallet\CustomerWalletManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Collects one period's price for a subscription AffilStack renews itself
 * (see Subscription::renewsThroughStripe()): the customer wallet pays first
 * — unless the customer switched "Use wallet for renewals" off, or the
 * wallet is in a different currency — and the saved card covers the rest.
 *
 * The wallet row stays locked while the card is charged, so the wallet
 * share can't be spent elsewhere in between; and the wallet is only debited
 * once the card has succeeded, so a declined card never takes a partial
 * payment from the wallet.
 */
class SubscriptionRenewalCharger
{
    public function __construct(
        protected CustomerWalletManager $wallets,
        protected PaymentGatewayManager $gateways,
    ) {}

    /**
     * What a charge would use right now, without charging anything.
     *
     * @return array{price_minor: int, currency: string, wallet_minor: int, card_minor: int, method: ?PaymentMethod}
     */
    public function plan(Subscription $subscription): array
    {
        $subscription->loadMissing('plan', 'user', 'paymentMethod');
        $price = $subscription->renewalPriceCents();
        $currency = strtoupper((string) ($subscription->plan?->currency ?: 'USD'));
        $wallet = $this->wallets->walletFor($subscription->user)->fresh();
        $walletShare = $this->walletShare($wallet, $price, $currency);

        return [
            'price_minor' => $price,
            'currency' => $currency,
            'wallet_minor' => $walletShare,
            'card_minor' => $price - $walletShare,
            'method' => $this->cardFor($subscription),
        ];
    }

    /**
     * @return array{success: bool, message: string, transaction: ?PaymentTransaction}
     */
    public function charge(Subscription $subscription, string $transactionType, string $walletType, string $description): array
    {
        $subscription->loadMissing('plan', 'user', 'paymentMethod');
        $user = $subscription->user;

        if (! $user || ! $subscription->plan) {
            return ['success' => false, 'message' => 'The subscription has no customer or plan.', 'transaction' => null];
        }

        $price = $subscription->renewalPriceCents();
        $currency = strtoupper((string) ($subscription->plan->currency ?: 'USD'));
        $wallet = $this->wallets->walletFor($user);

        return DB::transaction(function () use ($subscription, $user, $price, $currency, $wallet, $transactionType, $walletType, $description) {
            $locked = CustomerWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $walletShare = $this->walletShare($locked, $price, $currency);
            $cardShare = $price - $walletShare;
            $method = null;
            $cardResult = [];

            if ($cardShare > 0) {
                $method = $this->cardFor($subscription);

                if (! $method) {
                    return ['success' => false, 'message' => 'No saved card to charge.', 'transaction' => null];
                }

                try {
                    $cardResult = $this->gateways->get($method->gateway)->chargeSavedToken($method, $cardShare, $currency, $description);
                } catch (Throwable $e) {
                    report($e);
                    $cardResult = ['success' => false, 'message' => 'The card could not be charged.'];
                }

                if (! ($cardResult['success'] ?? false)) {
                    return ['success' => false, 'message' => (string) ($cardResult['message'] ?? 'The card was declined.'), 'transaction' => null];
                }

                $method->update(['last_used_at' => now()]);
            }

            try {
                $transaction = PaymentTransaction::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'type' => $transactionType,
                    'gateway' => $method?->gateway ?? 'wallet',
                    'gateway_tx_id' => $cardResult['reference'] ?? null,
                    'gateway_reference' => $cardResult['reference'] ?? null,
                    'tx_ref' => 'affilstack_renewal_'.Str::uuid(),
                    'plan_id' => $subscription->plan_id,
                    'billing_cycle' => $subscription->billing_cycle,
                    'amount_cents' => $price,
                    'currency' => $currency,
                    'status' => 'successful',
                    'raw_payload' => array_filter([
                        'wallet_minor' => $walletShare,
                        'card_minor' => $cardShare,
                        'card' => $cardResult['raw'] ?? null,
                    ], fn ($value) => $value !== null),
                    'processed_at' => now(),
                ]);

                if ($walletShare > 0) {
                    $this->wallets->debit($user, $walletShare, $walletType, $description, $transaction);
                }
            } catch (Throwable $e) {
                // The card was charged but recording it failed — loud, so a
                // person can refund or apply it by hand.
                Log::critical('Renewal charge succeeded at the gateway but could not be recorded', [
                    'subscription_id' => $subscription->id,
                    'gateway' => $method?->gateway,
                    'reference' => $cardResult['reference'] ?? null,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            return ['success' => true, 'message' => 'Charged.', 'transaction' => $transaction];
        });
    }

    protected function walletShare(CustomerWallet $wallet, int $price, string $currency): int
    {
        if (! $wallet->use_for_renewals || $wallet->currency !== $currency || $wallet->balance_minor <= 0) {
            return 0;
        }

        return min($wallet->balance_minor, $price);
    }

    /**
     * The subscription's own card, else the customer's default card — only
     * card types on a gateway that can charge a saved card.
     */
    public function cardFor(Subscription $subscription): ?PaymentMethod
    {
        $candidates = array_filter([$subscription->paymentMethod, $subscription->user?->defaultPaymentMethod()]);

        foreach ($candidates as $method) {
            if ($method->user_id === $subscription->user_id
                && $method->type === 'card'
                && filled($method->gateway_token)
                && in_array($method->gateway, (array) config('customer_wallet.trial.card_gateways'), true)
                && $this->gateways->has($method->gateway)) {
                return $method;
            }
        }

        return null;
    }
}
