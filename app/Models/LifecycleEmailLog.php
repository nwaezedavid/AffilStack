<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's copy of one lifecycle email for one occurrence. The unique
 * (user, email, occurrence) index is what makes every campaign idempotent:
 * the dispatcher claims a row with insertOrIgnore BEFORE queueing the
 * send, so a re-run of the hourly command (or two overlapping workers)
 * can never send the same thing twice.
 *
 * occurrence is:
 *   birthday      — the year ("2026"), once per year
 *   festive       — the festival's date ("2026-12-25"), once per occurrence
 *                   (a lunar festival can fall twice in one Gregorian year)
 *   reengagement  — "spell:<unix time of last activity>", once per
 *                   inactivity spell; coming back starts a new spell
 */
#[Fillable(['user_id', 'lifecycle_email_id', 'type', 'occurrence', 'local_date', 'status', 'bonus_credits_granted', 'error', 'sent_at'])]
class LifecycleEmailLog extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'sent_at' => 'datetime',
            'bonus_credits_granted' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lifecycleEmail(): BelongsTo
    {
        return $this->belongsTo(LifecycleEmail::class);
    }
}
