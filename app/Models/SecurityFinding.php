<?php

namespace App\Models;

use Database\Factories\SecurityFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One issue Tom (the Security Agent) found during a daily scan — see
 * SecurityScanService. `fix_action` (set deterministically by the check
 * that produced this finding, never by the AI) is what SecurityFixExecutor
 * actually runs once a super-admin approves and schedules it.
 */
#[Fillable(['fingerprint', 'category', 'title', 'severity', 'evidence', 'ai_summary', 'ai_suggested_fix', 'fix_action', 'status', 'agent_task_id', 'detected_at'])]
class SecurityFinding extends Model
{
    /** @use HasFactory<SecurityFindingFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_FIXED = 'fixed';

    public const STATUS_DISMISSED = 'dismissed';

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'fix_action' => 'array',
            'detected_at' => 'datetime',
        ];
    }

    public function agentTask(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class);
    }

    public function isFixable(): bool
    {
        return ! empty($this->fix_action['type'] ?? null);
    }
}
