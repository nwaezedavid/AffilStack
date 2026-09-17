<?php

namespace App\Models;

use Database\Factories\ReferralEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One commissionable event per payment (first_payment or renewal), created
 * by ReferralService::recordCommission() from the single hook point that
 * catches both — PaymentProcessor::activateSubscription(). Admins move
 * status pending -> approved (or reject a bad one) via the Filament
 * resource; the only path to "paid" is ReferralPayoutService, which
 * cascades every event attached to a processed ReferralPayout — an event's
 * status is never hand-set to "paid" directly, so "paid" always means a
 * real payout batch with a reference exists.
 */
#[Fillable(['referral_id', 'payment_transaction_id', 'event_type', 'amount_cents', 'currency', 'status', 'occurred_at', 'referral_payout_id'])]
class ReferralEvent extends Model
{
    /** @use HasFactory<ReferralEventFactory> */
    use HasFactory;

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

    public function payout(): BelongsTo
    {
        return $this->belongsTo(ReferralPayout::class, 'referral_payout_id');
    }
}
