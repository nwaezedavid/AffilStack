<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task #3: one "paste their reply, get an AI-drafted response" exchange —
 * see App\Services\Social\LinkedInReplyAssistantService, the only place
 * these are created.
 */
#[Fillable(['user_id', 'offer_id', 'their_message', 'draft_reply'])]
class LinkedinReplyDraft extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
