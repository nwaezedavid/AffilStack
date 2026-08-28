<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'amount', 'balance_after', 'reason', 'reference_type', 'reference_id'])]
class CreditLedger extends Model
{
    public $table = 'credit_ledger';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
