<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One contact's place in one campaign: queued until the sender gets to it,
 * then sent (with steps_sent counting the initial email + follow-ups),
 * failed, skipped (no email / plan limit), unsubscribed or replied. Unique
 * per (campaign, contact) so a contact is never emailed the same campaign
 * twice, however many times autopilot or "send to new" runs.
 */
#[Fillable([
    'crm_campaign_id', 'crm_contact_id', 'user_id', 'status', 'source', 'steps_sent',
    'next_step_due_at', 'last_sent_at', 'replied_at', 'opening_line', 'error_message',
])]
class CrmCampaignRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'next_step_due_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CrmCampaign::class, 'crm_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CrmEmailSend::class);
    }

    public function latestSend(): HasOne
    {
        return $this->hasOne(CrmEmailSend::class)->latestOfMany();
    }

    /**
     * The single status shown in campaign history: replied and unsubscribed
     * outrank engagement, which outranks plain delivery.
     */
    public function displayStatus(): string
    {
        if (in_array($this->status, ['replied', 'unsubscribed', 'failed', 'skipped', 'queued'], true)) {
            return $this->status;
        }

        $sends = $this->relationLoaded('sends') ? $this->sends : $this->sends()->get();

        if ($sends->whereNotNull('first_clicked_at')->isNotEmpty()) {
            return 'clicked';
        }

        if ($sends->whereNotNull('opened_at')->isNotEmpty()) {
            return 'opened';
        }

        return 'sent';
    }
}
