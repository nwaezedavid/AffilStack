<?php

namespace App\Models;

use App\Services\Agents\SamAgentService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'assigned_to', 'subject', 'category', 'priority', 'status',
    'csat_rating', 'last_reply_at',
])]
class SupportTicket extends Model
{
    public const DONE_STATUSES = ['resolved', 'closed'];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    /**
     * Sam (the Support Agent) notifies the customer the moment a ticket
     * newly becomes resolved/closed — from wherever the status change comes
     * from (the admin table today, potentially an API later). Fires only on
     * the transition into a done state, never on every save, and never on a
     * ticket that was already done (e.g. reopening then re-closing counts
     * as a fresh transition, which is the desired behavior).
     */
    protected static function booted(): void
    {
        static::updated(function (SupportTicket $ticket): void {
            if (! $ticket->wasChanged('status')) {
                return;
            }

            $becameDone = in_array($ticket->status, self::DONE_STATUSES, true);
            $wasDone = in_array($ticket->getOriginal('status'), self::DONE_STATUSES, true);

            if ($becameDone && ! $wasDone) {
                app(SamAgentService::class)->notifyTicketStatusChanged($ticket);
            }
        });
    }
}
