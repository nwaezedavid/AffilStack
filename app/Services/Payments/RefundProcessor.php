<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use App\Services\Referrals\ReferralService;
use Illuminate\Support\Facades\Log;

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
    public function __construct(protected ReferralService $referrals) {}

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

        return $transaction;
    }
}
