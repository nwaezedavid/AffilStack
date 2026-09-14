<?php

namespace App\Console;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Stringable;

/**
 * Wraps a scheduled Event so every run is recorded to scheduled_task_runs —
 * this is what powers the "Scheduled Task Runs" admin page (System group),
 * so an admin can see whether the cron is actually firing everything on
 * time instead of just assuming it is. Wrap every entry in
 * routes/console.php with ScheduleMonitoring::track(...).
 */
class ScheduleMonitoring
{
    public static function track(Event $event, string $task): Event
    {
        return $event
            ->before(function () use ($task) {
                ScheduledTaskRun::create([
                    'task' => $task,
                    'status' => 'running',
                    'started_at' => now(),
                ]);
            })
            ->onSuccess(function () use ($task) {
                static::finish($task, 'success');
            })
            ->onFailure(function (Stringable $output) use ($task) {
                static::finish($task, 'failed', $output->limit(2000)->toString());
            });
    }

    protected static function finish(string $task, string $status, ?string $output = null): void
    {
        ScheduledTaskRun::query()
            ->where('task', $task)
            ->where('status', 'running')
            ->latest('started_at')
            ->first()
            ?->update([
                'status' => $status,
                'finished_at' => now(),
                'output' => $output,
            ]);
    }
}
