<?php

namespace App\Console\Commands\GitHub;

use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use App\Services\GitHub\GitHubSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('github:sync')]
#[Description('Auto-sync this application\'s own code to the connected GitHub repository, when enabled from System > GitHub Sync')]
class SyncRepository extends Command
{
    public function handle(GitHubSyncService $service): void
    {
        $settings = GitHubSyncSetting::current();

        if (! $settings->is_enabled) {
            $this->info('GitHub auto-sync is not enabled — nothing to do.');

            return;
        }

        $run = $service->sync($settings, GitHubSyncRun::TRIGGER_SCHEDULED);

        $this->info("GitHub sync finished: {$run->status} — {$run->message}");
    }
}
