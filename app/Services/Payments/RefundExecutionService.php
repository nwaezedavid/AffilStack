<?php

namespace App\Services\Payments;

use App\Models\CreditLedger;
use App\Models\PaymentTransaction;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Credits\CreditManager;
use InvalidArgumentException;

/**
 * Audit item #8 — the only place a refund actually happens. Deliberately
 * fully automatic (no admin approval step): RefundEligibilityService's rule
 * is strict and non-discretionary on purpose, so there's nothing here for a
 * human to decide. Ties together the gateway-specific refund call, the
 * RefundRequest audit row, subscription cancellation, and the credit
 * clawback — the referral commission reversal is handled by
 * RefundProcessor::process(), reused here rather than duplicated so a
 * refund issued from this flow and one an admin issues by hand in a
 * gateway's own dashboard (which arrives later as a webhook) always update
 * the same PaymentTransaction the same way.
 */
class RefundExecutionService
{
    public function __construct(
        protected RefundEligibilityService $eligibility,
        protected RefundProcessor $refunds,
        protected CreditManager $credits,
        protected FlutterwaveGateway $flutterwave,
        protected StripeGateway $stripe,
        protected PaystackGateway $paystack,
        protected PayPalGateway $paypal,
    ) {}

    public function request(User $user): RefundRequest
    {
        $transaction = $this->eligibility->eligibleTransaction($user);

        if (! $transaction) {
            throw new InvalidArgumentException(
                'This account is no longer eligible for an automatic refund — either the 48-hour window has passed or credits have already been used.'
            );
        }

        $result = $this->issueGatewayRefund($transaction);

        if (! $result['success']) {
            return RefundRequest::create([
                'user_id' => $user->id,
                'payment_transaction_id' => $transaction->id,
                'status' => 'ineligible',
                'reason' => $result['message'],
                'gateway' => $transaction->gateway,
                'amount_cents' => $transaction->amount_cents,
                'currency' => $transaction->currency,
                'requested_at' => now(),
            ]);
        }

        // Same code path a "refund happened" webhook drives — updates the
        // transaction to 'refunded' and reverses the referral commission,
        // idempotently. See class docblock.
        $this->refunds->process($transaction->gateway, [
            'kind' => 'refunded',
            'gateway_tx_ids' => array_values(array_filter([$transaction->gateway_tx_id])),
            'gateway_references' => array_values(array_filter([$transaction->gateway_reference])),
            'amount' => $transaction->amount(),
            'currency' => $transaction->currency,
            'raw' => $result['raw'],
        ]);

        $this->cancelSubscription($transaction);
        $this->clawBackCredits($user, $transaction);

        return RefundRequest::create([
            'user_id' => $user->id,
            'payment_transaction_id' => $transaction->id,
            'status' => 'refunded',
            'reason' => '48-hour no-usage refund policy',
            'gateway' => $transaction->gateway,
            'gateway_reference' => $result['reference'] ?? null,
            'amount_cents' => $transaction->amount_cents,
            'currency' => $transaction->currency,
            'requested_at' => now(),
        ]);
    }

    /**
     * @return array{success: bool, message: string, reference?: string, raw: array<string, mixed>}
     */
    protected function issueGatewayRefund(PaymentTransaction $transaction): array
    {
        return match ($transaction->gateway) {
            'flutterwave' => $this->flutterwave->refund((string) $transaction->gateway_tx_id, $transaction->amount_cents),
            'stripe' => $this->stripe->refund((string) $transaction->gateway_tx_id, $transaction->amount_cents),
            'paystack' => $this->paystack->refund((string) ($transaction->gateway_reference ?: $transaction->tx_ref), $transaction->amount_cents),
            'paypal' => $this->paypal->refund((string) $transaction->gateway_tx_id, $transaction->amount_cents, $transaction->currency),
            default => ['success' => false, 'message' => "No refund support for gateway '{$transaction->gateway}'.", 'raw' => []],
        };
    }

    protected function cancelSubscription(PaymentTransaction $transaction): void
    {
        $subscription = $transaction->subscription;

        if ($subscription && $subscription->status !== 'canceled') {
            $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        }
    }

    /**
     * Claws back exactly the credits this refunded subscription granted —
     * never a blanket reset to zero — found by the same reference_type/
     * reference_id CreditManager::grant() stamped on the ledger row.
     * Eligibility already guarantees zero usage, so the full granted amount
     * is guaranteed to still be sitting in the balance untouched.
     */
    protected function clawBackCredits(User $user, PaymentTransaction $transaction): void
    {
        $subscription = $transaction->subscription;

        if (! $subscription) {
            return;
        }

        $granted = (int) CreditLedger::where('user_id', $user->id)
            ->where('reference_type', $subscription->getMorphClass())
            ->where('reference_id', $subscription->id)
            ->where('amount', '>', 0)
            ->sum('amount');

        if ($granted > 0) {
            $this->credits->spend($user, $granted, 'refund_clawback', $subscription);
        }
    }
}
