<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One execution record of a GitHub sync attempt (a manual "Sync now" click
 * or the daily scheduled job) — written by App\Services\GitHub\GitHubSyncService
 * so an admin can see real sync history (Filament: System > GitHub Sync),
 * distinct from ScheduledTaskRun, which only records whether the
 * `github:sync` artisan command itself ran, not what each sync actually did.
 */
#[Fillable(['status', 'trigger', 'message', 'commit_sha'])]
class GitHubSyncRun extends Model
{
    // See GitHubSyncSetting::$table for why this is pinned explicitly.
    protected $table = 'github_sync_runs';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';
}
