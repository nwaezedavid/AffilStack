<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One commissionable event per payment (first_payment or renewal), created
 * by ReferralService::recordCommission() from the single hook point that
 * catches both — PaymentProcessor::activateSubscription(). Admins move
 * status pending -> approved -> paid via the Filament resource; nothing
 * else in the app writes to status after creation.
 */
#[Fillable(['referral_id', 'payment_transaction_id', 'event_type', 'amount_cents', 'currency', 'status', 'occurred_at'])]
class ReferralEvent extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
