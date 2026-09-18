<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signed movement in the affiliate payout wallet (audit item #5) — see
 * the create_wallet_transactions_table migration and PayoutWalletService.
 * Never updated or deleted after creation.
 */
#[Fillable(['currency', 'type', 'amount_cents', 'gateway', 'referral_payout_id', 'reference', 'note', 'created_by_id'])]
class WalletTransaction extends Model
{
    public function referralPayout(): BelongsTo
    {
        return $this->belongsTo(ReferralPayout::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
