<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for "Connect LinkedIn" (task #3) —
 * see Filament: Site > LinkedIn Connect and
 * App\Services\Social\LinkedInOAuthService. Same shape as
 * GoogleOauthSetting: credentials encrypted at rest.
 */
#[Fillable(['is_enabled', 'credentials'])]
class LinkedInOauthSetting extends Model
{
    // Eloquent's naming convention would derive "linked_in_oauth_settings"
    // from this class name (it splits before each capital, so "LinkedIn"
    // becomes "linked_in") — pinned explicitly to match the migration and
    // the plain "linkedin_..." spelling used everywhere else in this
    // feature (routes, the LinkedinReplyDraft model, etc.).
    protected $table = 'linkedin_oauth_settings';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
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

    public function isAvailable(): bool
    {
        return $this->is_enabled && $this->credential('client_id') && $this->credential('client_secret');
    }
}
