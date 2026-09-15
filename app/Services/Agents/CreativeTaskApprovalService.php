<?php

namespace App\Services\Agents;

use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * The one place a pending Tony (the Creative Agent) draft turns into a
 * live change — a real security boundary, not just a UI affordance, so it
 * holds even if something calls this directly rather than through the
 * "Approve & publish" button. Deliberately runs CreativeTaskExecutor
 * synchronously right after approving (unlike Tom's fixes, a content
 * change has no maintenance-window reason to wait for the next scheduler
 * pass) — ExecuteDueAgentTasks still exists as a safety net in case that
 * synchronous call is ever interrupted.
 */
class CreativeTaskApprovalService
{
    public function __construct(protected CreativeTaskExecutor $executor) {}

    public function approveAndPublish(AgentTask $task, User $superAdmin): void
    {
        if (! $superAdmin->isSuperAdmin()) {
            throw new AuthorizationException('Only the super-admin can approve one of Tony\'s drafts before it goes live.');
        }

        if ($task->status !== AgentTask::STATUS_PENDING) {
            throw new InvalidArgumentException('Only a pending draft can be approved.');
        }

        $task->update([
            'status' => AgentTask::STATUS_SCHEDULED,
            'scheduled_at' => now(),
            'approved_by_id' => $superAdmin->id,
            'approved_at' => now(),
        ]);

        $this->executor->execute($task->fresh());
    }

    public function decline(AgentTask $task, User $admin, ?string $reason = null): void
    {
        if ($task->status !== AgentTask::STATUS_PENDING) {
            throw new InvalidArgumentException('Only a pending draft can be declined.');
        }

        $task->update([
            'status' => AgentTask::STATUS_DECLINED,
            'result' => $reason,
        ]);
    }
}
