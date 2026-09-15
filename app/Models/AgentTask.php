<?php

namespace App\Models;

use Database\Factories\AgentTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A permission request from one of the AI agents (Tom/Sam/Brain/Tony) to do
 * something that touches the live platform. Nothing here executes itself —
 * AgentTask only ever moves from 'pending' to 'scheduled' when a
 * super-admin approves it (see SecurityFindingResource), and only the
 * `agents:execute-due-tasks` command, running on its own schedule, actually
 * carries it out once `scheduled_at` arrives.
 */
#[Fillable(['agent', 'type', 'title', 'summary', 'payload', 'requested_by_id', 'risk_level', 'status', 'scheduled_at', 'scheduled_until', 'approved_by_id', 'approved_at', 'executed_at', 'result'])]
class AgentTask extends Model
{
    /** @use HasFactory<AgentTaskFactory> */
    use HasFactory;

    public const AGENT_SECURITY = 'security';

    public const AGENT_SUPPORT = 'support';

    public const AGENT_MARKETING = 'marketing';

    public const AGENT_CREATIVE = 'creative';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'scheduled_until' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function securityFinding(): HasOne
    {
        return $this->hasOne(SecurityFinding::class);
    }

    public function isDue(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }
}
