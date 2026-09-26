<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for the UGC video overhaul — see
 * Filament: Content > UGC Video Settings. AffilStack's own HeyGen account
 * renders every user's video; users spend platform credits (config
 * credits.ugc_video), never a HeyGen bill of their own.
 */
#[Fillable(['is_enabled', 'credentials', 'verified_at', 'verification_status', 'verification_message'])]
class HeyGenSetting extends Model
{
    protected $table = 'heygen_settings';

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

    public function hasApiKey(): bool
    {
        return filled($this->credential('api_key'));
    }

    /**
     * Whether a user's "Generate video" click should actually be allowed to
     * spend real HeyGen minutes — the master switch has to be on AND the
     * key has to have passed a live verification, not just be present.
     */
    public function isReady(): bool
    {
        return $this->is_enabled && $this->verification_status === 'success';
    }
}
