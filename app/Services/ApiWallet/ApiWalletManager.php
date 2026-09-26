<?php

namespace App\Services\ApiWallet;

use App\Models\ApiWalletTransaction;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single choke point for every movement in the API usage prepay wallet —
 * real money, prepaid by the user specifically to call the API, entirely
 * separate from credits_balance (the AI-generation credits a subscription
 * grants monthly for dashboard use). Mirrors CreditManager's shape closely,
 * plus the one thing that system doesn't need: attemptAutoRecharge(), since
 * a subscription renews itself but a wallet only refills when its owner
 * asked it to via a saved card.
 *
 * Every method resolves $user to its billableUser() first, same reasoning
 * as CreditManager — a team seat's metered API calls are paid from (and
 * checked against) whoever actually funds the account.
 */
class ApiWalletManager
{
    public function __construct(protected PaymentGatewayManager $gateways) {}

    public function balance(User $user): int
    {
        return $user->billableUser()->fresh()->api_wallet_balance_cents;
    }

    public function hasEnough(User $user, int $amountCents): bool
    {
        return $this->balance($user) >= $amountCents;
    }

    /**
     * Debits a metered API call. If the balance is short, tries exactly one
     * auto-recharge first (a no-op if it isn't configured) before giving up
     * — so a user with auto-recharge on on never sees a failed request just
     * because their wallet happened to run dry mid-call.
     *
     * @throws InsufficientApiWalletBalanceException
     */
    public function charge(User $user, int $amountCents, string $reason, ?Model $reference = null): ApiWalletTransaction
    {
        $billable = $user->billableUser();

        if (! $this->hasEnough($billable, $amountCents)) {
            // Serialized per user: N concurrent low-balance calls must make
            // at most one off-session card charge, not N. Whoever waits on
            // the lock re-checks the balance the winner just topped up.
            try {
                Cache::lock('api-wallet-recharge:'.$billable->id, 60)->block(20, function () use ($billable, $amountCents) {
                    if (! $this->hasEnough($billable, $amountCents)) {
                        $this->attemptAutoRecharge($billable->fresh());
                    }
                });
            } catch (LockTimeoutException) {
                // Another recharge is still in flight — fall through to the
                // normal balance check below.
            }
        }

        return DB::transaction(function () use ($billable, $amountCents, $reason, $reference) {
            $locked = User::whereKey($billable->id)->lockForUpdate()->firstOrFail();

            if ($locked->api_wallet_balance_cents < $amountCents) {
                throw new InsufficientApiWalletBalanceException(
                    "User {$billable->id} has {$locked->api_wallet_balance_cents}c in their API wallet, needs {$amountCents}c for '{$reason}'."
                );
            }

            $threshold = $this->lowBalanceThresholdCents();
            $wasAboveThreshold = $locked->api_wallet_balance_cents > $threshold;

            $locked->decrement('api_wallet_balance_cents', $amountCents);

            $transaction = ApiWalletTransaction::create([
                'user_id' => $billable->id,
                'type' => 'usage',
                'amount_cents' => -$amountCents,
                'balance_after_cents' => $locked->api_wallet_balance_cents,
                'description' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);

            // Fired on the crossing, not on every low-balance call after it
            // — a user who ignores the first notice shouldn't get it again
            // on every single request until they top up.
            if ($wasAboveThreshold && $locked->api_wallet_balance_cents <= $threshold) {
                app(WebhookDispatcher::class)->dispatch($billable, 'api_wallet.low_balance', [
                    'balance_cents' => $locked->api_wallet_balance_cents,
                    'threshold_cents' => $threshold,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * A completed checkout (CreditTopupController-style one-time purchase)
     * crediting the wallet — see PaymentProcessor's 'api_wallet_topup' type.
     */
    public function topUp(User $user, int $amountCents, string $reason, ?Model $reference = null): ApiWalletTransaction
    {
        return $this->credit($user, $amountCents, 'topup', $reason, $reference, notify: true);
    }

    /**
     * Reverses a charge for an API call that was accepted (and billed) but
     * then failed before doing the work it was billed for — see
     * MeterApiUsage, which calls this for any non-2xx response.
     */
    public function refund(User $user, int $amountCents, string $reason, ?Model $reference = null): ApiWalletTransaction
    {
        return $this->credit($user, $amountCents, 'refund', $reason, $reference, notify: false);
    }

    protected function credit(User $user, int $amountCents, string $type, string $reason, ?Model $reference, bool $notify): ApiWalletTransaction
    {
        $billable = $user->billableUser();

        return DB::transaction(function () use ($billable, $amountCents, $type, $reason, $reference, $notify) {
            $locked = User::whereKey($billable->id)->lockForUpdate()->firstOrFail();
            $locked->increment('api_wallet_balance_cents', $amountCents);

            $transaction = ApiWalletTransaction::create([
                'user_id' => $billable->id,
                'type' => $type,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $locked->api_wallet_balance_cents,
                'description' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);

            if ($notify) {
                app(WebhookDispatcher::class)->dispatch($billable, 'api_wallet.topped_up', [
                    'type' => $type,
                    'amount_cents' => $amountCents,
                    'balance_after_cents' => $locked->api_wallet_balance_cents,
                ]);
            }

            return $transaction;
        });
    }

    protected function lowBalanceThresholdCents(): int
    {
        return (int) config('api_billing.low_balance_threshold_cents', 500);
    }

    /**
     * One automatic top-up against the user's saved auto-recharge payment
     * method — a silent no-op (never throws) whenever it isn't fully
     * configured, has no card on file, or the gateway declines/doesn't
     * support it, so charge() above always falls through cleanly to its own
     * normal insufficient-balance error either way.
     */
    public function attemptAutoRecharge(User $user): bool
    {
        if (! $user->api_wallet_auto_recharge_enabled || ! $user->api_wallet_auto_recharge_amount_cents) {
            return false;
        }

        $method = $user->apiWalletPaymentMethod;

        if (! $method) {
            return false;
        }

        $currency = (string) config('api_billing.currency', 'USD');
        $amountCents = $user->api_wallet_auto_recharge_amount_cents;

        $result = $this->gateways->get($method->gateway)->chargeSavedToken(
            $method,
            $amountCents,
            $currency,
            'AffilStack API wallet auto-recharge',
        );

        if (! ($result['success'] ?? false)) {
            Log::warning('API wallet auto-recharge failed', [
                'user_id' => $user->id,
                'gateway' => $method->gateway,
                'message' => $result['message'] ?? null,
            ]);

            return false;
        }

        $paymentTransaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'type' => 'api_wallet_auto_recharge',
            'gateway' => $method->gateway,
            'tx_ref' => 'affilstack_api_autorecharge_'.Str::uuid(),
            'gateway_tx_id' => $result['reference'] ?? null,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'status' => 'successful',
            'raw_payload' => $result['raw'] ?? null,
            'processed_at' => now(),
        ]);

        $this->credit($user, $amountCents, 'auto_recharge', 'api_wallet_auto_recharge', $paymentTransaction, notify: true);

        return true;
    }
}
