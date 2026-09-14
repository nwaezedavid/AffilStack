<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One execution record of a scheduled console command, written by
 * App\Console\ScheduleMonitoring so admins can see — from Filament,
 * System > Scheduled Task Runs — whether the cron is actually running
 * everything on time, not just assume it is.
 */
#[Fillable(['task', 'status', 'output', 'started_at', 'finished_at'])]
class ScheduledTaskRun extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
