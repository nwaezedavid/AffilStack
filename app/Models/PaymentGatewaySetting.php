<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-entered configuration for one payment gateway (stripe or
 * flutterwave) — see Filament: Billing > Payment Gateways. `credentials`
 * is encrypted at rest via Laravel's built-in 'encrypted:array' cast, so
 * API keys never sit in the database as plain text.
 */
#[Fillable(['gateway', 'is_enabled', 'credentials', 'last_verified_at', 'last_verification_status', 'last_verification_message'])]
class PaymentGatewaySetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_verified_at' => 'datetime',
        ];
    }

    public static function forGateway(string $gateway): self
    {
        return static::firstOrCreate(['gateway' => $gateway]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }
}
