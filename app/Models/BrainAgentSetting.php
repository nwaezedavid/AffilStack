<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for Brain (the Marketing Agent) —
 * see Filament: AI Agents > Brain Settings. Credentials are encrypted at
 * rest, same pattern as PaymentGatewaySetting/GoogleOauthSetting.
 */
#[Fillable([
    'is_enabled', 'credentials',
    'anthropic_verified_at', 'anthropic_verification_status', 'anthropic_verification_message',
    'meta_mcp_verified_at', 'meta_mcp_verification_status', 'meta_mcp_verification_message',
])]
class BrainAgentSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'anthropic_verified_at' => 'datetime',
            'meta_mcp_verified_at' => 'datetime',
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

    /**
     * Both connections have to be verified successfully, and the master
     * switch on, before Brain is allowed to touch real ad spend — drafting
     * (which only needs the Anthropic key) stays available regardless.
     */
    public function canLaunchLiveCampaigns(): bool
    {
        return $this->is_enabled
            && $this->anthropic_verification_status === 'success'
            && $this->meta_mcp_verification_status === 'success';
    }

    public function hasAnthropicKey(): bool
    {
        return filled($this->credential('anthropic_api_key'));
    }
}
