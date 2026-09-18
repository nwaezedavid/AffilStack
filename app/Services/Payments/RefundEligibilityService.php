<?php

namespace App\Services\Payments;

use App\Models\CreditLedger;
use App\Models\PaymentTransaction;
use App\Models\User;

/**
 * Audit item #8's refund eligibility rule — deliberately a single, strict,
 * non-discretionary check rather than an admin approval queue: paid within
 * the last 48 hours AND has never spent a single credit. There is no path
 * for a human to bend this, on purpose — a discretionary policy can always
 * be argued with, an objective automatic rule can't, and that's what
 * actually protects the business financially (see RefundExecutionService,
 * which is the only thing allowed to act on a "yes" from here).
 *
 * "Zero usage" reuses CreditManager::spend()'s own ledger rather than
 * introducing a separate usage flag — every spend already writes a
 * negative-amount CreditLedger row, so its absence is already a clean,
 * pre-existing signal that this account hasn't cost the platform anything
 * yet.
 */
class RefundEligibilityService
{
    public const WINDOW_HOURS = 48;

    public function eligibleTransaction(User $user): ?PaymentTransaction
    {
        $transaction = $this->latestRefundableTransaction($user);

        return $transaction && $this->isEligible($user, $transaction) ? $transaction : null;
    }

    public function isEligible(User $user, ?PaymentTransaction $transaction = null): bool
    {
        $transaction ??= $this->latestRefundableTransaction($user);

        if (! $transaction || ! $transaction->processed_at) {
            return false;
        }

        if ($transaction->processed_at->lt(now()->subHours(self::WINDOW_HOURS))) {
            return false;
        }

        return $this->hasZeroUsage($user);
    }

    public function hasZeroUsage(User $user): bool
    {
        return CreditLedger::where('user_id', $user->id)
            ->where('amount', '<', 0)
            ->doesntExist();
    }

    /**
     * How much of the 48-hour window is left, for display on the billing
     * page — never used for the eligibility decision itself, only to tell
     * the user why the button will disappear soon.
     */
    public function hoursRemaining(PaymentTransaction $transaction): int
    {
        if (! $transaction->processed_at) {
            return 0;
        }

        $deadline = $transaction->processed_at->addHours(self::WINDOW_HOURS);

        return $deadline->isPast() ? 0 : (int) ceil(now()->diffInMinutes($deadline) / 60);
    }

    protected function latestRefundableTransaction(User $user): ?PaymentTransaction
    {
        return PaymentTransaction::where('user_id', $user->id)
            ->where('status', 'successful')
            ->where('type', 'subscription')
            ->latest('processed_at')
            ->first();
    }
}
