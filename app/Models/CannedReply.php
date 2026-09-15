<?php

namespace App\Models;

use Database\Factories\CannedReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable reply template offered to staff when they reply to a support
 * ticket — see MessagesRelationManager. `manual` ones are written directly
 * by staff/admins; `ai_suggested` ones are drafted by Sam (the Support
 * Agent) from recurring patterns in resolved tickets (see
 * SamAgentService::suggestTemplates()) and start life as `suggested`,
 * invisible to the reply picker until a staff/admin reviews and activates
 * them. This is ordinary content curation, not a codebase change, so any
 * admin/support user can activate one — it does not need the super-admin
 * approval that gates Tom's and Tony's platform-level changes.
 */
#[Fillable(['title', 'body', 'status', 'source', 'usage_count', 'last_used_at', 'ai_rationale'])]
class CannedReply extends Model
{
    /** @use HasFactory<CannedReplyFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_ARCHIVED = 'archived';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI_SUGGESTED = 'ai_suggested';

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
