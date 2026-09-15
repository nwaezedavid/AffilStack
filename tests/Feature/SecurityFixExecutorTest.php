<?php

namespace Tests\Feature;

use App\Models\AgentTask;
use App\Models\SecurityFinding;
use App\Models\User;
use App\Notifications\MaintenanceCompleted;
use App\Services\Agents\MaintenanceNotificationService;
use App\Services\Agents\SecurityFixExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Never touches the project's real .env — envPath() is overridden to a
 * throwaway file for every test here (see SecurityFixExecutor::envPath()).
 */
class SecurityFixExecutorTest extends TestCase
{
    use RefreshDatabase;

    protected string $fakeEnvPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeEnvPath = storage_path('framework/testing/fake-env-'.uniqid().'.env');
        File::ensureDirectoryExists(dirname($this->fakeEnvPath));
        File::put($this->fakeEnvPath, "APP_NAME=AffilStack\nAPP_DEBUG=true\n");
    }

    protected function tearDown(): void
    {
        File::delete($this->fakeEnvPath);
        parent::tearDown();
    }

    /**
     * A class-name partial mock (needed so execute()'s own internal
     * $this->envPath()/$this->assertHealthy() calls actually hit the
     * stubs below — an instance-wrapping mock wouldn't intercept those
     * internal self-calls). Mockery skips the constructor for a
     * class-name mock, so the notifier dependency is set manually
     * afterwards via a bound closure rather than through __construct.
     */
    protected function executorWithFakeEnv(?\Closure $extraStubs = null): SecurityFixExecutor
    {
        $mock = Mockery::mock(SecurityFixExecutor::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();

        (function () {
            $this->notifier = app(MaintenanceNotificationService::class);
        })->bindTo($mock, SecurityFixExecutor::class)();

        $mock->shouldReceive('envPath')->andReturn($this->fakeEnvPath);

        if ($extraStubs) {
            $extraStubs($mock);
        }

        return $mock;
    }

    protected function taskFor(SecurityFinding $finding): AgentTask
    {
        $task = AgentTask::factory()->scheduled()->create([
            'payload' => ['security_finding_id' => $finding->id],
        ]);
        $finding->update(['status' => SecurityFinding::STATUS_SCHEDULED, 'agent_task_id' => $task->id]);

        return $task->fresh();
    }

    public function test_a_successful_env_fix_is_applied_and_the_task_and_finding_are_marked_completed(): void
    {
        Notification::fake();
        User::factory()->count(2)->create();

        $finding = SecurityFinding::factory()->create([
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
        ]);
        $task = $this->taskFor($finding);

        $this->executorWithFakeEnv()->execute($task);

        $this->assertStringContainsString('APP_DEBUG=false', File::get($this->fakeEnvPath));
        $this->assertSame(AgentTask::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame(SecurityFinding::STATUS_FIXED, $finding->fresh()->status);
        Notification::assertSentTimes(MaintenanceCompleted::class, 2);
    }

    public function test_a_fix_that_fails_its_health_check_is_rolled_back_automatically(): void
    {
        Notification::fake();
        $originalContents = File::get($this->fakeEnvPath);

        $finding = SecurityFinding::factory()->create([
            'fix_action' => ['type' => 'env_set', 'params' => ['key' => 'APP_DEBUG', 'value' => 'false']],
        ]);
        $task = $this->taskFor($finding);

        $this->executorWithFakeEnv(function ($mock) {
            $mock->shouldReceive('assertHealthy')->andThrow(new RuntimeException('simulated post-fix breakage'));
        })->execute($task);

        $this->assertSame($originalContents, File::get($this->fakeEnvPath));
        $this->assertSame(AgentTask::STATUS_ROLLED_BACK, $task->fresh()->status);
        $this->assertStringContainsString('simulated post-fix breakage', $task->fresh()->result);
        Notification::assertNothingSent();
    }

    public function test_a_task_with_no_matching_finding_is_marked_failed_without_touching_any_file(): void
    {
        $task = AgentTask::factory()->scheduled()->create(['payload' => ['security_finding_id' => 999999]]);
        $originalContents = File::get($this->fakeEnvPath);

        $this->executorWithFakeEnv()->execute($task);

        $this->assertSame(AgentTask::STATUS_FAILED, $task->fresh()->status);
        $this->assertSame($originalContents, File::get($this->fakeEnvPath));
    }
}
