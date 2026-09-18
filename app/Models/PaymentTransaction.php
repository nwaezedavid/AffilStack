<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'subscription_id', 'pending_signup_id', 'credit_package_id', 'type', 'gateway', 'gateway_tx_id', 'gateway_reference', 'tx_ref',
    'amount_cents', 'currency', 'status', 'raw_payload', 'processed_at',
])]
class PaymentTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function pendingSignup(): BelongsTo
    {
        return $this->belongsTo(PendingSignup::class);
    }

    public function creditPackage(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class);
    }

    public function amount(): float
    {
        return $this->amount_cents / 100;
    }
}
