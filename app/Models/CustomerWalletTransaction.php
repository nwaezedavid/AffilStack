<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One signed movement in a customer wallet. Append-only: rows are written by
 * CustomerWalletManager and can never be updated or deleted afterwards — a
 * mistake is corrected with a new, opposite row (an admin deduction or
 * credit), so the history always adds up to the balance.
 */
#[Fillable([
    'customer_wallet_id', 'user_id', 'type', 'amount_minor', 'balance_after_minor', 'currency',
    'description', 'note', 'reference_type', 'reference_id', 'created_by_user_id', 'idempotency_key',
])]
class CustomerWalletTransaction extends Model
{
    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'topup' => 'Top-up',
        'credit_pack' => 'Credit pack',
        'subscription' => 'Plan payment',
        'renewal' => 'Plan renewal',
        'trial_conversion' => 'First payment after trial',
        'card_check' => 'Card check (kept as balance)',
        'admin_credit' => 'Credit from AffilStack',
        'admin_refund' => 'Refund to wallet',
        'admin_deduction' => 'Correction',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Customer wallet transactions are append-only and cannot be edited.'));
        static::deleting(fn () => throw new LogicException('Customer wallet transactions are append-only and cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
