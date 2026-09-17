<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for TikTok publishing (task #2) —
 * see Filament: Content > TikTok Publishing and
 * App\Services\Social\TikTokPublishingService. approval_status tracks
 * TikTok's own Content Posting API audit — every new app defaults to a
 * capped/private scope until that passes.
 */
#[Fillable(['is_enabled', 'credentials', 'approval_status', 'approval_notes'])]
class TikTokSetting extends Model
{
    // Eloquent's convention would derive "tik_tok_settings" from this class
    // name — pinned explicitly to match the migration's plain "tiktok_..."
    // spelling.
    protected $table = 'tiktok_settings';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function isAvailable(): bool
    {
        return $this->is_enabled && $this->credential('client_key') && $this->credential('client_secret');
    }

    public function isApprovedForPublishing(): bool
    {
        return $this->approval_status === 'approved';
    }
}
