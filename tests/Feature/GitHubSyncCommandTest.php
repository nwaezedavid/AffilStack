<?php

namespace Tests\Feature;

use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use App\Services\GitHub\GitHubSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The `github:sync` artisan command itself (wrapped in ScheduleMonitoring::
 * track() in routes/console.php, exactly like every other scheduled job in
 * this codebase, so its run history shows up under Filament's System >
 * Scheduled Task Runs). Never touches git — GitHubSyncService is swapped
 * for a mock so this stays focused on the command's one real decision:
 * whether auto-sync is enabled at all.
 */
class GitHubSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_sync_when_auto_sync_is_disabled(): void
    {
        GitHubSyncSetting::current()->update(['is_enabled' => false]);

        $mock = Mockery::mock(GitHubSyncService::class);
        $mock->shouldNotReceive('sync');
        $this->app->instance(GitHubSyncService::class, $mock);

        $this->artisan('github:sync')->assertExitCode(0);
    }

    public function test_it_syncs_with_the_scheduled_trigger_when_auto_sync_is_enabled(): void
    {
        GitHubSyncSetting::current()->update([
            'is_enabled' => true,
            'repo_owner' => 'acme',
            'repo_name' => 'affilistack',
            'credentials' => ['personal_access_token' => 'ghp_token'],
        ]);

        $mock = Mockery::mock(GitHubSyncService::class);
        $mock->shouldReceive('sync')
            ->once()
            ->with(Mockery::type(GitHubSyncSetting::class), GitHubSyncRun::TRIGGER_SCHEDULED)
            ->andReturn(new GitHubSyncRun([
                'status' => GitHubSyncRun::STATUS_SUCCESS,
                'trigger' => GitHubSyncRun::TRIGGER_SCHEDULED,
                'message' => 'Synced local changes to GitHub.',
                'commit_sha' => 'abc1234',
            ]));
        $this->app->instance(GitHubSyncService::class, $mock);

        $this->artisan('github:sync')->assertExitCode(0);
    }
}
