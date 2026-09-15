<?php

namespace App\Services\Agents;

use App\Models\AgentTask;
use App\Models\User;
use App\Notifications\MaintenanceCompleted;
use App\Notifications\ScheduledMaintenanceNotice;
use Illuminate\Support\Facades\Notification;

/**
 * The "notify all users" half of Tom's (the Security Agent's) hand-off to
 * Sam (the Support Agent) — described in the platform's AI-agent plan as
 * Tom instructing Sam to broadcast a scheduled-maintenance notice. Sam
 * (agent #2) isn't built yet, so this lives here for now; once Sam exists
 * it should own outbound user communication and this service becomes the
 * thing Tom calls into Sam for, not a standalone broadcaster.
 */
class MaintenanceNotificationService
{
    public function notifyScheduled(AgentTask $task): void
    {
        $reason = $task->title;

        User::query()->chunkById(200, function ($users) use ($reason, $task) {
            Notification::send($users, new ScheduledMaintenanceNotice(
                $reason,
                $task->scheduled_at,
                $task->scheduled_until,
            ));
        });
    }

    public function notifyCompleted(AgentTask $task): void
    {
        $reason = $task->title;

        User::query()->chunkById(200, function ($users) use ($reason) {
            Notification::send($users, new MaintenanceCompleted($reason));
        });
    }
}
