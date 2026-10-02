<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of one real email sent to a CRM contact from the platform (CRM
 * dashboard phase) — see CrmEmailService, which is the only place these are
 * created. Distinct from Generation (the AI-drafted content): this is what
 * was actually sent, plus delivery/open/click tracking.
 */
#[Fillable([
    'user_id', 'crm_contact_id', 'generation_id', 'sequence_step', 'subject', 'body',
    'link_destination', 'status', 'error_message', 'tracking_token',
    'sent_at', 'opened_at', 'open_count', 'first_clicked_at', 'click_count',
    'kind', 'crm_campaign_id', 'crm_campaign_recipient_id', 'message_id', 'in_reply_to',
    'provider_message_id', 'provider_thread_id',
])]
class CrmEmailSend extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'first_clicked_at' => 'datetime',
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

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CrmCampaign::class, 'crm_campaign_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CrmCampaignRecipient::class, 'crm_campaign_recipient_id');
    }

    public function recordOpen(): void
    {
        $this->update([
            'opened_at' => $this->opened_at ?? now(),
            'open_count' => $this->open_count + 1,
        ]);
    }

    public function recordClick(): void
    {
        $this->update([
            'first_clicked_at' => $this->first_clicked_at ?? now(),
            'click_count' => $this->click_count + 1,
        ]);
    }
}
