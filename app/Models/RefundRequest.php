<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's refund request and its final, system-decided outcome — see
 * RefundEligibilityService and the create_refund_requests_table migration
 * comment for why there's no approval step.
 */
#[Fillable(['user_id', 'payment_transaction_id', 'status', 'reason', 'gateway', 'gateway_reference', 'amount_cents', 'currency', 'requested_at'])]
class RefundRequest extends Model
{
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function wasRefunded(): bool
    {
        return $this->status === 'refunded';
    }
}
