<?php

namespace App\Services\Agents;

use App\Models\AgentTask;
use App\Models\SecurityFinding;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The one place a fixable SecurityFinding turns into a scheduled AgentTask.
 * Deliberately not left as logic inline in the Filament action — a
 * super-admin-only approval is a real security boundary, not just a UI
 * affordance, so it has to hold even if something calls this directly
 * (an artisan command, a future API) rather than through the button.
 */
class SecurityFindingApprovalService
{
    public function __construct(protected SamAgentService $notifier) {}

    public function approveAndSchedule(
        SecurityFinding $finding,
        User $approver,
        Carbon $scheduledAt,
        Carbon $scheduledUntil,
    ): AgentTask {
        if (! $approver->isSuperAdmin()) {
            throw new AuthorizationException('Only the super-admin can approve an AI agent fix.');
        }

        if (! $finding->isFixable()) {
            throw new InvalidArgumentException('This finding has no safe automatic fix to schedule.');
        }

        if ($finding->status !== SecurityFinding::STATUS_OPEN) {
            throw new InvalidArgumentException('This finding is not open.');
        }

        $task = AgentTask::create([
            'agent' => AgentTask::AGENT_SECURITY,
            'type' => 'security_fix',
            'title' => $finding->title,
            'summary' => $finding->ai_summary,
            'payload' => ['security_finding_id' => $finding->id],
            'risk_level' => $finding->severity,
            'status' => AgentTask::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'scheduled_until' => $scheduledUntil,
            'approved_by_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $finding->update(['status' => SecurityFinding::STATUS_SCHEDULED, 'agent_task_id' => $task->id]);

        $this->notifier->notifyScheduled($task);

        return $task;
    }
}
