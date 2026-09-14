<?php

use App\Console\ScheduleMonitoring;
use App\Models\ScheduledTaskRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every scheduled task below is wrapped in ScheduleMonitoring::track() so
// its run history shows up in Filament under System > Scheduled Task Runs
// — an admin can see it actually ran, not just assume it did. New
// scheduled work (subscription renewals, UGC video cleanup, agent tasks,
// etc.) should follow the same pattern as each of those features is built.
// ->withoutOverlapping() guards every entry so a slow run never causes a
// second overlapping run to start.

ScheduleMonitoring::track(
    Schedule::command('signups:prune-expired')->daily()->withoutOverlapping(),
    'signups:prune-expired',
);

// Self-maintenance: keep the run-history table itself from growing forever.
Schedule::call(fn () => ScheduledTaskRun::where('created_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('prune-scheduled-task-runs')
    ->withoutOverlapping();
