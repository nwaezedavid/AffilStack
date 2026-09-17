<?php

namespace App\Models;

use App\Services\Webhooks\WebhookDispatcher;
use Database\Factories\ReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per referred user (unique on referred_user_id — see migration).
 * Created by ReferralService::createReferralForNewUser() the moment a
 * referred signup's payment completes (PaymentProcessor::completeSignup()),
 * then flipped to "converted" by recordCommission() once the first payment
 * clears. Commission events live in ReferralEvent, raw clicks in
 * ReferralClick — this row is just the relationship + lifecycle status.
 */
#[Fillable(['referrer_id', 'referred_user_id', 'status', 'converted_at'])]
class Referral extends Model
{
    /** @use HasFactory<ReferralFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
        ];
    }

    /**
     * Audit gap #7 (outbound webhooks — "referral.converted").
     */
    protected static function booted(): void
    {
        static::updated(function (Referral $referral) {
            if ($referral->wasChanged('status') && $referral->status === 'converted') {
                app(WebhookDispatcher::class)->dispatch($referral->referrer, 'referral.converted', [
                    'referral_id' => $referral->id,
                    'referred_user_id' => $referral->referred_user_id,
                    'converted_at' => $referral->converted_at?->toIso8601String(),
                ]);
            }
        });
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class);
    }
}
