<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One signed movement in a user's API usage prepay wallet — see the
 * create_api_wallet_transactions_table migration and ApiWalletManager, the
 * single choke point that creates these. Never updated or deleted after
 * creation; users.api_wallet_balance_cents is always kept equal to the
 * running total, checked/reconciled from this ledger.
 */
#[Fillable(['user_id', 'type', 'amount_cents', 'balance_after_cents', 'description', 'reference_type', 'reference_id'])]
class ApiWalletTransaction extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
