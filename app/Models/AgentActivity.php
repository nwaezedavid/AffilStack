<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One thing an AI agent did (or proposes) for an account — the feed on the
 * AI Agents page and the raw material of the Weekly briefing.
 */
#[Fillable(['user_id', 'agent', 'action', 'summary', 'subject_type', 'subject_id', 'meta', 'status'])]
class AgentActivity extends Model
{
    public const STATUS_DONE = 'done';

    public const STATUS_NEEDS_APPROVAL = 'needs_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISMISSED = 'dismissed';

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
