<?php

namespace App\Services\Agents;

use App\Models\AgentTask;
use App\Models\SecurityFinding;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Runs the fix_action attached to a SecurityFinding, unattended, once its
 * AgentTask reaches its scheduled time — see ExecuteDueAgentTasks. Every
 * code-touching fix is preceded by a backup of the exact file(s) it's about
 * to change and followed by a health check; a failed health check restores
 * that backup automatically rather than leaving the platform in a broken
 * state. Nothing here ever asks a second time — the super-admin's approval
 * at scheduling time is the only human step, per how Tom is specified to
 * work.
 */
class SecurityFixExecutor
{
    public function __construct(protected SamAgentService $notifier) {}

    public function execute(AgentTask $task): void
    {
        $finding = $task->securityFinding;
        $type = $finding?->fix_action['type'] ?? null;

        if (! $finding || ! $type) {
            $task->update(['status' => AgentTask::STATUS_FAILED, 'result' => 'No executable fix_action on this task.']);

            return;
        }

        $task->update(['status' => AgentTask::STATUS_RUNNING]);

        try {
            $backup = match ($type) {
                'env_set' => $this->backupEnvFile(),
                'composer_update' => $this->backupComposerFiles(),
                default => throw new RuntimeException("Unknown fix_action type: {$type}"),
            };

            match ($type) {
                'env_set' => $this->applyEnvSet($finding->fix_action['params']),
                'composer_update' => $this->applyComposerUpdate($finding->fix_action['params']),
            };

            $this->assertHealthy();

            $task->update([
                'status' => AgentTask::STATUS_COMPLETED,
                'executed_at' => now(),
                'result' => 'Fix applied and the platform passed its post-fix health check.',
            ]);
            $finding->update(['status' => SecurityFinding::STATUS_FIXED]);
            $this->notifier->notifyCompleted($task);
        } catch (\Throwable $e) {
            Log::error('Tom: fix execution failed, rolling back.', ['task_id' => $task->id, 'error' => $e->getMessage()]);

            $this->restore($backup ?? []);

            $task->update([
                'status' => AgentTask::STATUS_ROLLED_BACK,
                'executed_at' => now(),
                'result' => 'Fix failed its health check and was rolled back automatically: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function backupEnvFile(): array
    {
        $path = $this->envPath();

        return [$path => File::exists($path) ? File::get($path) : ''];
    }

    /**
     * Overridable purely so tests can point this at a throwaway file
     * instead of the real .env — this genuinely rewrites whatever path it's
     * given, so that separation matters.
     */
    protected function envPath(): string
    {
        return base_path('.env');
    }

    /**
     * @return array<string, string>
     */
    protected function backupComposerFiles(): array
    {
        return [
            base_path('composer.json') => File::get(base_path('composer.json')),
            base_path('composer.lock') => File::exists(base_path('composer.lock')) ? File::get(base_path('composer.lock')) : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $backup
     */
    protected function restore(array $backup): void
    {
        foreach ($backup as $absolutePath => $contents) {
            File::put($absolutePath, $contents);
        }

        if (array_key_exists(base_path('composer.lock'), $backup)) {
            Process::path(base_path())->run('composer install --no-interaction --no-scripts');
        }

        Artisan::call('config:clear');
    }

    /**
     * @param  array{key: string, value: string}  $params
     */
    protected function applyEnvSet(array $params): void
    {
        $path = $this->envPath();
        $contents = File::exists($path) ? File::get($path) : '';
        $key = $params['key'];
        $value = $params['value'];

        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        $contents = preg_match($pattern, $contents)
            ? preg_replace($pattern, "{$key}={$value}", $contents)
            : rtrim($contents)."\n{$key}={$value}\n";

        File::put($path, $contents);
        Artisan::call('config:clear');
    }

    /**
     * @param  array{package: string, version: string}  $params
     */
    protected function applyComposerUpdate(array $params): void
    {
        $result = Process::path(base_path())
            ->timeout(300)
            ->run("composer require {$params['package']}:{$params['version']} --no-interaction --no-scripts");

        if (! $result->successful()) {
            throw new RuntimeException('composer require failed: '.$result->errorOutput());
        }
    }

    /**
     * The bar for "everything is running smoothly": the app still boots and
     * the database is still reachable. Deliberately lightweight — this runs
     * unattended in production, not the full test suite.
     */
    protected function assertHealthy(): void
    {
        if (Artisan::call('about', ['--only' => 'environment']) !== 0) {
            throw new RuntimeException('Application no longer boots after the fix.');
        }

        DB::connection()->getPdo();
    }
}
