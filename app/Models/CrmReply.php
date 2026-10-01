<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email a CRM contact sent back, detected in the user's own mailbox
 * (IMAP or the Gmail API) and matched to the send it answers — see
 * App\Services\Crm\Inbox\ReplyIngestor.
 */
#[Fillable([
    'user_id', 'crm_contact_id', 'crm_email_send_id', 'crm_campaign_id', 'crm_campaign_recipient_id',
    'provider', 'external_id', 'message_id', 'in_reply_to', 'references', 'from_email', 'from_name',
    'subject', 'body_text', 'body_clean', 'matched_by', 'received_at', 'read_at',
])]
class CrmReply extends Model
{
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function send(): BelongsTo
    {
        return $this->belongsTo(CrmEmailSend::class, 'crm_email_send_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CrmCampaign::class, 'crm_campaign_id');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
