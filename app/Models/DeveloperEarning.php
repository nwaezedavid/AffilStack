<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A developer's share of one add-on payment (or a refund taking it back).
 * Waits out the refund window, then is paid with the partner payouts.
 */
#[Fillable(['developer_profile_id', 'addon_id', 'addon_install_id', 'payment_transaction_id', 'type', 'gross_cents', 'share_percent', 'amount_cents', 'currency', 'status', 'available_at', 'referral_payout_id', 'occurred_at'])]
class DeveloperEarning extends Model
{
    protected function casts(): array
    {
        return [
            'gross_cents' => 'integer',
            'amount_cents' => 'integer',
            'share_percent' => 'decimal:2',
            'available_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class, 'developer_profile_id');
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function install(): BelongsTo
    {
        return $this->belongsTo(AddonInstall::class, 'addon_install_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(ReferralPayout::class, 'referral_payout_id');
    }
}
