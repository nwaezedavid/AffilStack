<?php

namespace App\Console\Commands\Agents;

use App\Models\AgentTask;
use App\Services\Agents\SecurityFixExecutor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The only place any AI agent's approved action actually runs. Everything
 * upstream of this (a scan finding an issue, a super-admin approving and
 * scheduling it) only ever creates or updates an AgentTask row — nothing
 * executes until its scheduled_at arrives and this command picks it up.
 */
#[Signature('agents:execute-due-tasks')]
#[Description('Execute AI-agent tasks whose scheduled time has arrived')]
class ExecuteDueAgentTasks extends Command
{
    public function handle(SecurityFixExecutor $securityExecutor): void
    {
        $due = AgentTask::where('status', AgentTask::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $task) {
            match ($task->agent) {
                AgentTask::AGENT_SECURITY => $securityExecutor->execute($task),
                // Sam/Brain/Tony don't schedule executable tasks yet — a
                // task from any of them landing here today is a bug, not a
                // silent no-op, so it's marked failed rather than ignored.
                default => $task->update([
                    'status' => AgentTask::STATUS_FAILED,
                    'result' => "No executor registered for agent '{$task->agent}' yet.",
                ]),
            };
        }

        $this->info("Executed {$due->count()} due agent task(s).");
    }
}
