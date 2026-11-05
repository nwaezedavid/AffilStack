<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-only 7-day free trial invite. The trial is never offered
 * publicly: the signed, single-use link in the invite email is the only way
 * to start one. See TrialInviteService for the lifecycle.
 */
#[Fillable([
    'name', 'email', 'plan_id', 'billing_cycle', 'status', 'sent_by_user_id', 'user_id',
    'subscription_id', 'expires_at', 'accepted_at', 'cancelled_at', 'converted_at',
])]
class TrialInvite extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_SENT => 'Sent',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_CONVERTED => 'Converted',
        self::STATUS_EXPIRED => 'Expired',
    ];

    public const TRIAL_DAYS = 7;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Whether the emailed link can still be used to start the trial.
     */
    public function isRedeemable(): bool
    {
        return $this->status === self::STATUS_SENT && $this->expires_at->isFuture();
    }

    public function priceCents(): int
    {
        return $this->billing_cycle === 'yearly' ? (int) $this->plan?->price_yearly_cents : (int) $this->plan?->price_monthly_cents;
    }
}
