<?php

namespace App\Jobs;

use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use App\Services\GitHub\GitHubSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * "Sync now" from the GitHub Sync page. Git runs as a separate program,
 * which needs PHP's proc_open; doing it in the background worker (whose cron
 * lifts Hostinger's function limits for the command line only) lets the
 * website itself keep proc_open switched off — so a web shell, if one ever
 * got in, couldn't start programs either.
 */
class RunGitHubSync implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public string $trigger = GitHubSyncRun::TRIGGER_MANUAL) {}

    public function handle(GitHubSyncService $service): void
    {
        $service->sync(GitHubSyncSetting::current(), $this->trigger);
    }
}
