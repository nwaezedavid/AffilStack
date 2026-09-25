<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved, reusable payment method (audit item #2) — captured
 * automatically after a successful payment, never entered directly. See
 * PaymentMethodRecorder for how each gateway's response is turned into one
 * of these.
 */
#[Fillable([
    'user_id', 'gateway', 'type', 'brand', 'last4', 'exp_month', 'exp_year',
    'label', 'country', 'gateway_customer_id', 'gateway_token', 'is_default', 'last_used_at',
])]
class PaymentMethod extends Model
{
    protected function casts(): array
    {
        return [
            'gateway_token' => 'encrypted',
            'is_default' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function display(): string
    {
        if ($this->type === 'paypal') {
            return 'PayPal — '.($this->label ?? 'connected account');
        }

        $brand = $this->brand ? ucfirst($this->brand) : ucfirst($this->gateway);

        return trim("{$brand} •••• {$this->last4}".($this->exp_month && $this->exp_year ? " (exp. {$this->exp_month}/{$this->exp_year})" : ''));
    }
}
