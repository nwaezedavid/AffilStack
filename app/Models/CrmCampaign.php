<?php

namespace App\Models;

use Database\Factories\CrmCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cold-email campaign to one CRM list (usually one product's
 * prospects). The subject/body are templates with merge fields (see
 * App\Services\Crm\CampaignMergeFields); each contact it reaches gets a
 * CrmCampaignRecipient row, so the history of who received what — and
 * which list members are still "new" — is always queryable.
 *
 * Lifecycle: draft → (Approve and Send) sending → sent; paused when the user
 * pauses it or the email connection fails verification. With autopilot on,
 * every contact newly added to the list is sent the campaign automatically.
 */
#[Fillable([
    'user_id', 'crm_list_id', 'offer_id', 'name', 'subject', 'body', 'status', 'autopilot',
    'personalize_opening', 'follow_up_steps', 'tone', 'credits_spent', 'generated_at',
    'approved_at', 'last_sent_at', 'paused_reason',
])]
class CrmCampaign extends Model
{
    /** @use HasFactory<CrmCampaignFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'approved', 'sending', 'sent', 'paused'];

    protected function casts(): array
    {
        return [
            'autopilot' => 'boolean',
            'personalize_opening' => 'boolean',
            'follow_up_steps' => 'array',
            'generated_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(CrmList::class, 'crm_list_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CrmCampaignRecipient::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CrmEmailSend::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CrmReply::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isLaunched(): bool
    {
        return in_array($this->status, ['approved', 'sending', 'sent', 'paused'], true);
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Follow-up steps the user kept, normalised and in order.
     *
     * @return array<int, array{delay_days: int, subject: string, body: string}>
     */
    public function followUps(): array
    {
        return collect($this->follow_up_steps ?? [])
            ->filter(fn ($step) => is_array($step) && trim((string) ($step['body'] ?? '')) !== '')
            ->map(fn (array $step) => [
                'delay_days' => max(1, (int) ($step['delay_days'] ?? 3)),
                'subject' => (string) ($step['subject'] ?? ''),
                'body' => (string) $step['body'],
            ])
            ->values()
            ->all();
    }

    /**
     * List members who have joined since this campaign was launched and have
     * no recipient row yet — what "Send to N new contacts" sends to.
     */
    public function newContactsQuery()
    {
        return $this->list->contacts()
            ->whereNotIn('crm_contacts.id', $this->recipients()->select('crm_contact_id'));
    }
}
