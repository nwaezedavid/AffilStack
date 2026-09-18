<?php

namespace App\Services\Referrals;

use App\Models\ReferralPayout;
use App\Models\User;
use App\Models\WalletTransaction;
use InvalidArgumentException;

/**
 * The affiliate payout wallet (audit item #5) — an admin tops this up from
 * the Filament "Payout Wallet" screen (money they've actually deposited into
 * their Flutterwave/PayPal balance elsewhere), and
 * PayoutDisbursementService draws it down automatically when disbursing an
 * approved ReferralPayout. Every movement is an immutable WalletTransaction
 * row; the balance is never a stored column, always the sum of that ledger,
 * so it can never drift out of sync with what actually happened.
 */
class PayoutWalletService
{
    public function balance(string $currency): int
    {
        return (int) WalletTransaction::where('currency', $currency)->sum('amount_cents');
    }

    /**
     * @return array<string, int> currency => balance_cents, for every
     *                            currency that has ever had a movement
     */
    public function balances(): array
    {
        return WalletTransaction::query()
            ->selectRaw('currency, SUM(amount_cents) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function topUp(string $currency, int $amountCents, User $admin, ?string $reference = null, ?string $note = null): WalletTransaction
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Top-up amount must be greater than zero.');
        }

        return WalletTransaction::create([
            'currency' => strtoupper($currency),
            'type' => 'top_up',
            'amount_cents' => $amountCents,
            'reference' => $reference,
            'note' => $note,
            'created_by_id' => $admin->id,
        ]);
    }

    public function hasSufficientBalance(string $currency, int $amountCents): bool
    {
        return $this->balance(strtoupper($currency)) >= $amountCents;
    }

    /**
     * Records the wallet side of an automatic disbursement — always called
     * only after the gateway has already accepted the transfer, never
     * before, so a failed API call never leaves a debit with nothing to
     * show for it. See PayoutDisbursementService::disburse().
     */
    public function recordDisbursement(ReferralPayout $payout, string $gateway, string $reference): WalletTransaction
    {
        return WalletTransaction::create([
            'currency' => $payout->currency,
            'type' => 'payout',
            'amount_cents' => -$payout->amount_cents,
            'gateway' => $gateway,
            'referral_payout_id' => $payout->id,
            'reference' => $reference,
            'note' => 'Auto-disbursed to '.$payout->user->name.' via '.ucfirst($gateway),
        ]);
    }
}
