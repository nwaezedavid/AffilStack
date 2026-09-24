<?php

namespace Tests\Feature;

use App\Models\GitHubSyncRun;
use App\Models\GitHubSyncSetting;
use App\Services\GitHub\GitHubSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

/**
 * Never touches this application's real .git — workingCopyPath() and
 * remoteUrl() are overridden per test (the same Mockery partial-mock
 * pattern as SecurityFixExecutor::envPath()) to point every sync at a
 * throwaway working copy and a throwaway local `file://` bare repo
 * standing in for GitHub. verifyConnection() is tested separately via
 * Http::fake() — the real GitHub API is never called from any test here.
 */
class GitHubSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $workDir;

    protected string $bareDir;

    protected function setUp(): void
    {
        parent::setUp();

        $unique = 'gh-sync-test-'.uniqid();
        $this->workDir = storage_path("framework/testing/{$unique}/work");
        $this->bareDir = storage_path("framework/testing/{$unique}/bare.git");

        File::ensureDirectoryExists($this->workDir);
        Process::path($this->workDir)->run(['git', 'init', '-q']);
        Process::path($this->workDir)->run(['git', 'config', 'user.email', 'test@example.com']);
        Process::path($this->workDir)->run(['git', 'config', 'user.name', 'Test']);
        File::put($this->workDir.'/readme.txt', "hello\n");
        Process::path($this->workDir)->run(['git', 'add', '-A']);
        Process::path($this->workDir)->run(['git', 'commit', '-q', '-m', 'initial commit']);

        Process::run(['git', 'init', '--bare', '-q', $this->bareDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->workDir));

        parent::tearDown();
    }

    protected function serviceForThrowawayRepo(): GitHubSyncService
    {
        $mock = Mockery::mock(GitHubSyncService::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('workingCopyPath')->andReturn($this->workDir);
        $mock->shouldReceive('remoteUrl')->andReturn('file://'.$this->bareDir);

        return $mock;
    }

    protected function connectedSettings(): GitHubSyncSetting
    {
        $settings = GitHubSyncSetting::current();
        $settings->update([
            'repo_owner' => 'acme',
            'repo_name' => 'affilistack',
            'branch' => 'main',
            'credentials' => ['personal_access_token' => 'ghp_SuperSecretRawToken123'],
            'is_enabled' => true,
        ]);

        return $settings->fresh();
    }

    public function test_a_successful_sync_commits_and_pushes_pending_changes_to_the_remote(): void
    {
        File::put($this->workDir.'/new-feature.php', "<?php\n// a real code change\n");

        $settings = $this->connectedSettings();

        $run = $this->serviceForThrowawayRepo()->sync($settings, GitHubSyncRun::TRIGGER_MANUAL);

        $this->assertSame(GitHubSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertNotNull($run->commit_sha);
        $this->assertSame(GitHubSyncRun::TRIGGER_MANUAL, $run->trigger);

        $settings->refresh();
        $this->assertSame('success', $settings->last_sync_status);
        $this->assertSame($run->commit_sha, $settings->last_sync_commit_sha);
        $this->assertNotNull($settings->last_sync_at);

        // The bare "remote" actually received the pushed commit.
        $log = Process::path($this->bareDir)->run(['git', 'log', '--oneline', 'main']);
        $this->assertTrue($log->successful());
        $this->assertStringContainsString('Automated sync from AffiliStack', $log->output());

        $show = Process::path($this->bareDir)->run(['git', 'show', 'main:new-feature.php']);
        $this->assertStringContainsString('a real code change', $show->output());
    }

    public function test_a_sync_with_nothing_new_still_reports_success_without_committing_again(): void
    {
        $settings = $this->connectedSettings();

        $first = $this->serviceForThrowawayRepo()->sync($settings, GitHubSyncRun::TRIGGER_SCHEDULED);
        $second = $this->serviceForThrowawayRepo()->sync($settings->fresh(), GitHubSyncRun::TRIGGER_SCHEDULED);

        $this->assertSame(GitHubSyncRun::STATUS_SUCCESS, $first->status);
        $this->assertSame(GitHubSyncRun::STATUS_SUCCESS, $second->status);
        $this->assertStringContainsString('up to date', $second->message);
        $this->assertSame($first->commit_sha, $second->commit_sha);
    }

    /**
     * The single most important guarantee this feature makes: the token is
     * used only as a transient, per-invocation `git push` credential and is
     * never written to disk anywhere under .git — not in config, not in
     * packed-refs, not in any log file.
     */
    public function test_the_personal_access_token_never_touches_disk_anywhere_under_git(): void
    {
        File::put($this->workDir.'/another-change.txt', 'a second real change');

        $settings = $this->connectedSettings();
        $token = $settings->credential('personal_access_token');

        $run = $this->serviceForThrowawayRepo()->sync($settings, GitHubSyncRun::TRIGGER_MANUAL);

        $this->assertSame(GitHubSyncRun::STATUS_SUCCESS, $run->status);

        $gitConfig = File::get($this->workDir.'/.git/config');
        $this->assertStringNotContainsString($token, $gitConfig);
        $this->assertStringNotContainsString(base64_encode('x-access-token:'.$token), $gitConfig);
        $this->assertStringNotContainsString($this->workDir.'/.git/config', ''); // sanity: path itself has no token

        foreach (File::allFiles($this->workDir.'/.git') as $file) {
            $contents = File::get($file->getPathname());
            $this->assertStringNotContainsString($token, $contents, "Token leaked into {$file->getPathname()}");
        }
    }

    /**
     * Defense-in-depth guard: a `.env` sitting in the pending changes
     * refuses the whole sync, before anything is committed or pushed —
     * never just trusting .gitignore (this repo's own .gitignore only
     * lists the three exact names .env/.env.backup/.env.production, not a
     * wildcard, and a throwaway test repo like this one has no .gitignore
     * covering it at all).
     */
    public function test_a_pending_env_file_change_refuses_the_sync_and_pushes_nothing(): void
    {
        Log::spy();

        File::put($this->workDir.'/.env', "APP_KEY=super-secret\nDB_PASSWORD=hunter2\n");
        File::put($this->workDir.'/normal-file.txt', 'an unrelated ordinary change');

        $settings = $this->connectedSettings();

        $run = $this->serviceForThrowawayRepo()->sync($settings, GitHubSyncRun::TRIGGER_MANUAL);

        $this->assertSame(GitHubSyncRun::STATUS_BLOCKED, $run->status);
        $this->assertStringContainsString('.env', $run->message);
        $this->assertNull($run->commit_sha);

        $settings->refresh();
        $this->assertSame('blocked', $settings->last_sync_status);

        // Nothing new was committed locally...
        $log = Process::path($this->workDir)->run(['git', 'log', '--oneline']);
        $this->assertSame(1, count(array_filter(explode("\n", trim($log->output())))));

        // ...and the "remote" never received a push at all (no branch exists there yet).
        $remoteLog = Process::path($this->bareDir)->run(['git', 'log', '--oneline', 'main']);
        $this->assertFalse($remoteLog->successful());

        // The working tree was left exactly as found — even the unrelated file was unstaged again.
        $status = Process::path($this->workDir)->run(['git', 'status', '--porcelain']);
        $this->assertStringContainsString('.env', $status->output());
        $this->assertStringContainsString('normal-file.txt', $status->output());

        $token = $settings->credential('personal_access_token');
        Log::shouldHaveReceived('alert')
            ->once()
            ->withArgs(function (string $message, array $context) use ($token) {
                return str_contains($message, 'refused to sync')
                    && ! str_contains($message, $token)
                    && ! str_contains(json_encode($context), $token);
            });
    }

    public function test_sync_is_skipped_with_a_clear_message_when_credentials_are_incomplete(): void
    {
        $settings = GitHubSyncSetting::current();

        $run = $this->serviceForThrowawayRepo()->sync($settings);

        $this->assertSame(GitHubSyncRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('personal access token', $run->message);
    }

    public function test_a_failed_push_is_recorded_without_ever_including_the_raw_token_in_the_message(): void
    {
        File::put($this->workDir.'/change.txt', 'x');
        $settings = $this->connectedSettings();
        $token = $settings->credential('personal_access_token');

        $mock = Mockery::mock(GitHubSyncService::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('workingCopyPath')->andReturn($this->workDir);
        $mock->shouldReceive('remoteUrl')->andReturn('file:///nonexistent/path/does/not/exist.git');

        $run = $mock->sync($settings, GitHubSyncRun::TRIGGER_MANUAL);

        $this->assertSame(GitHubSyncRun::STATUS_FAILED, $run->status);
        $this->assertStringNotContainsString($token, $run->message);
    }

    public function test_verify_connection_reports_success_with_push_access(): void
    {
        Http::fake([
            'api.github.com/repos/acme/affilistack' => Http::response([
                'default_branch' => 'main',
                'permissions' => ['push' => true],
            ], 200),
        ]);

        $result = app(GitHubSyncService::class)->verifyConnection($this->connectedSettings());

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('acme/affilistack', $result['message']);
    }

    public function test_verify_connection_reports_an_invalid_token_on_401(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        $result = app(GitHubSyncService::class)->verifyConnection($this->connectedSettings());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('401', $result['message']);
    }

    public function test_verify_connection_reports_a_missing_repository_on_404(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

        $result = app(GitHubSyncService::class)->verifyConnection($this->connectedSettings());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('404', $result['message']);
    }

    public function test_verify_connection_flags_a_token_without_push_access(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'default_branch' => 'main',
                'permissions' => ['push' => false],
            ], 200),
        ]);

        $result = app(GitHubSyncService::class)->verifyConnection($this->connectedSettings());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('push access', $result['message']);
    }

    public function test_verify_connection_requires_a_token_and_repo_to_be_set_first(): void
    {
        $result = app(GitHubSyncService::class)->verifyConnection(GitHubSyncSetting::current());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', $result['message']);
    }
}
