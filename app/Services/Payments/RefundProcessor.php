<?php

namespace App\Services\Payments;

use App\Models\ApiWalletTransaction;
use App\Models\CreditLedger;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\CreditManager;
use App\Services\Referrals\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Audit gap #6: neither gateway webhook handled a refund or chargeback at
 * all — PaymentTransaction::status anticipated "refunded" in its own
 * migration comment but nothing ever set it, so a referrer kept commission
 * on money that had since been given back. Mirrors PaymentProcessor's
 * idempotent, lookup-then-update shape, but for the reverse direction, and
 * hands the actual commission reversal off to ReferralService so this stays
 * gateway-agnostic.
 */
class RefundProcessor
{
    public function __construct(protected ReferralService $referrals, protected CreditManager $credits) {}

    /**
     * @param  array{kind: string, gateway_tx_ids?: array<int, string>, gateway_references?: array<int, string>, amount?: float, currency?: string, raw: array<string, mixed>}  $event
     */
    public function process(string $gateway, array $event): ?PaymentTransaction
    {
        $txIds = array_values(array_filter($event['gateway_tx_ids'] ?? []));
        $references = array_values(array_filter($event['gateway_references'] ?? []));

        if (empty($txIds) && empty($references)) {
            return null;
        }

        $transaction = PaymentTransaction::where('gateway', $gateway)
            ->where('status', 'successful')
            ->where(function ($query) use ($txIds, $references) {
                if (! empty($txIds)) {
                    $query->orWhereIn('gateway_tx_id', $txIds);
                }

                if (! empty($references)) {
                    $query->orWhereIn('gateway_reference', $references);
                }
            })
            ->first();

        if (! $transaction) {
            // Not necessarily an error — a retried webhook for a transaction
            // this already reversed lands here too, since it's no longer
            // "successful" the second time around.
            Log::info('Refund/chargeback webhook for an unknown or already-reversed transaction', [
                'gateway' => $gateway, 'kind' => $event['kind'], 'gateway_tx_ids' => $txIds, 'gateway_references' => $references,
            ]);

            return null;
        }

        $status = $event['kind'] === 'charged_back' ? 'charged_back' : 'refunded';

        $transaction->update([
            'status' => $status,
            'processed_at' => now(),
        ]);

        $this->referrals->reverseCommission($transaction, $status);

        $this->revokePurchase($transaction);

        return $transaction;
    }

    /**
     * A refund or chargeback started at the gateway (not through our own
     * refund button) used to only relabel the transaction — the customer
     * kept the active plan, its credits, or the wallet top-up. Take back
     * what this payment bought, capped at what's still unspent: a
     * chargeback can't create a negative balance.
     */
    protected function revokePurchase(PaymentTransaction $transaction): void
    {
        $user = $transaction->user;

        if (! $user) {
            return;
        }

        if (in_array($transaction->type, ['subscription', 'renewal', 'signup'], true) && $transaction->subscription) {
            $subscription = $transaction->subscription;

            if ($subscription->status !== 'canceled') {
                $subscription->update(['status' => 'canceled', 'canceled_at' => now(), 'cancel_at_period_end' => false]);

                if ($subscription->gateway === 'stripe' && $subscription->gateway_subscription_id) {
                    try {
                        app(StripeGateway::class)->cancelSubscription($subscription->gateway_subscription_id);
                    } catch (Throwable $e) {
                        Log::error('Refunded subscription could not be canceled at Stripe — cancel it manually', [
                            'stripe_subscription_id' => $subscription->gateway_subscription_id, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $this->clawBackCredits($user, $this->creditsGrantedFor($user, $subscription), $transaction);
        }

        if ($transaction->type === 'credit_topup' && $transaction->creditPackage) {
            $this->clawBackCredits($user, (int) $transaction->creditPackage->credits, $transaction);
        }

        if (in_array($transaction->type, ['api_wallet_topup', 'api_wallet_auto_recharge'], true)) {
            $credited = (int) ($transaction->credited_amount_cents ?? $transaction->amount_cents);

            DB::transaction(function () use ($user, $credited, $transaction) {
                $locked = User::whereKey($user->billableUser()->id)->lockForUpdate()->first();
                $take = min($credited, max(0, (int) $locked->api_wallet_balance_cents));

                if ($take > 0) {
                    $locked->decrement('api_wallet_balance_cents', $take);

                    ApiWalletTransaction::create([
                        'user_id' => $locked->id,
                        'type' => 'reversal',
                        'amount_cents' => -$take,
                        'balance_after_cents' => $locked->api_wallet_balance_cents,
                        'description' => 'refund_clawback',
                        'reference_type' => $transaction->getMorphClass(),
                        'reference_id' => $transaction->id,
                    ]);
                }
            });
        }
    }

    protected function creditsGrantedFor(User $user, Subscription $subscription): int
    {
        // Only the latest grant — earlier periods were paid for separately.
        return (int) (CreditLedger::where('user_id', $user->billableUser()->id)
            ->where('reference_type', $subscription->getMorphClass())
            ->where('reference_id', $subscription->id)
            ->where('amount', '>', 0)
            ->latest('id')
            ->value('amount') ?? 0);
    }

    protected function clawBackCredits(User $user, int $credits, PaymentTransaction $transaction): void
    {
        $take = min($credits, $this->credits->balance($user));

        if ($take > 0) {
            $this->credits->spend($user, $take, 'refund_clawback', $transaction);
        }
    }
}
