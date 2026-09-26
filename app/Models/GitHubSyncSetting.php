<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for the built-in GitHub sync
 * feature ("automatically connect to GitHub... push any future updates
 * automatically") — see Filament: System > GitHub Sync and
 * App\Services\GitHub\GitHubSyncService. Same shape as
 * PaymentGatewaySetting/PartnerStackSetting: the personal access token
 * lives inside `credentials`, encrypted at rest via the 'encrypted:array'
 * cast, and is never stored anywhere as plain text. repo_owner/repo_name/
 * branch identify the repo but aren't secrets, so they stay plain columns
 * — only the token itself needs encryption.
 */
#[Fillable(['repo_owner', 'repo_name', 'branch', 'is_enabled', 'credentials', 'last_sync_status', 'last_sync_at', 'last_sync_commit_sha', 'last_sync_message'])]
class GitHubSyncSetting extends Model
{
    // Eloquent's naming convention would derive "git_hub_sync_settings" from
    // this class name (it splits before each capital, so "GitHub" becomes
    // "git_hub") — pinned explicitly to match the migration and the plain
    // "github_..." spelling used everywhere else in this feature (routes,
    // GitHubSyncRun, GitHubSyncService), the same fix LinkedInOauthSetting
    // already needed for the same reason.
    protected $table = 'github_sync_settings';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_sync_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        // Not firstOrCreate(['id' => 1]): 'id' isn't fillable, so the INSERT silently
        // used the next auto-increment value instead — and on MariaDB/MySQL that
        // isn't 1 once any insert has been rolled back — so every call after
        // that created a fresh empty row and saved settings looked lost.
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1] + ['is_enabled' => false, 'branch' => 'main']);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    /**
     * Everything GitHubSyncService needs before it can even attempt a
     * verify or a sync — checked up front so a half-filled form fails with
     * a clear message instead of a confusing git/HTTP error.
     */
    public function hasCredentials(): bool
    {
        return filled($this->credential('personal_access_token'))
            && filled($this->repo_owner)
            && filled($this->repo_name);
    }

    public function repoSlug(): string
    {
        return "{$this->repo_owner}/{$this->repo_name}";
    }

    public function isConnected(): bool
    {
        return $this->is_enabled && $this->last_sync_status === 'success';
    }
}
