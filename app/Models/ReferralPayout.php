<?php

namespace App\Models;

use Database\Factories\ReferralPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch of an affiliate's approved commissions requested for payout —
 * see App\Services\Referrals\ReferralPayoutService. payout_method/
 * payout_details are a snapshot of the affiliate's profile at the moment
 * they requested it, not a live reference, so a later profile edit never
 * changes where an in-flight payout is headed.
 */
#[Fillable(['user_id', 'amount_cents', 'currency', 'status', 'payout_method', 'payout_details', 'reference', 'note', 'requested_at', 'processed_at', 'processed_by_id'])]
class ReferralPayout extends Model
{
    /** @use HasFactory<ReferralPayoutFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payout_details' => 'encrypted:array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class);
    }

    public function isRequested(): bool
    {
        return $this->status === 'requested';
    }
}
