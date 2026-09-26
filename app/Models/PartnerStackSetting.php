<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for a future PartnerStack
 * connection — see Filament: Billing > PartnerStack. Deliberately
 * connection-only: the in-house referral/affiliate program (see
 * app/Services/Referrals) keeps running as the only active program.
 * This just lets the admin store and verify PartnerStack credentials
 * ahead of actually deciding how (or whether) to wire the two together.
 */
#[Fillable(['is_enabled', 'credentials', 'verified_at', 'verification_status', 'verification_message'])]
class PartnerStackSetting extends Model
{
    protected $table = 'partner_stack_settings';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        // Not firstOrCreate(['id' => 1]): 'id' isn't fillable, so the INSERT silently
        // used the next auto-increment value instead — and on MariaDB/MySQL that
        // isn't 1 once any insert has been rolled back — so every call after
        // that created a fresh empty row and saved settings looked lost.
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1] + ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function hasCredentials(): bool
    {
        return filled($this->credential('public_key')) && filled($this->credential('secret_key'));
    }

    public function isConnected(): bool
    {
        return $this->is_enabled && $this->verification_status === 'success';
    }
}
