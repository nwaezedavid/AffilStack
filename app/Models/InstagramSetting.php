<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for Instagram publishing (task
 * #2) — a Meta app (Facebook Login for Business) — see Filament:
 * Content > Instagram Publishing and
 * App\Services\Social\InstagramPublishingService. approval_status tracks
 * Meta's App Review for instagram_content_publish plus a verified
 * Business Manager, both required before real publishing works.
 */
#[Fillable(['is_enabled', 'credentials', 'approval_status', 'approval_notes'])]
class InstagramSetting extends Model
{
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
        return $this->is_enabled && $this->credential('app_id') && $this->credential('app_secret');
    }

    public function isApprovedForPublishing(): bool
    {
        return $this->approval_status === 'approved';
    }
}
