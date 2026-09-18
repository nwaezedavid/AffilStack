<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'google_id', 'password', 'plan_id', 'billing_cycle', 'tx_ref', 'status', 'expires_at', 'referred_by_user_id', 'refund_policy_accepted_at'])]
#[Hidden(['password'])]
class PendingSignup extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'refund_policy_accepted_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }
}
