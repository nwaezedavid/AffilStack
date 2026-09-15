<?php

namespace App\Models;

use App\Services\Agents\SamAgentService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['support_ticket_id', 'user_id', 'is_staff', 'message'])]
class SupportTicketMessage extends Model
{
    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every staff reply is delivered to the ticket owner by Sam (the
     * Support Agent) — mail + dashboard notification — regardless of where
     * the reply was created from (the admin Messages relation manager
     * today, potentially other paths later).
     */
    protected static function booted(): void
    {
        static::created(function (SupportTicketMessage $message): void {
            if ($message->is_staff) {
                app(SamAgentService::class)->notifyTicketReplied($message);
            }
        });
    }
}
