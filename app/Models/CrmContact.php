<?php

namespace App\Models;

use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'is_sandbox', 'name', 'company', 'title', 'email', 'phone', 'website',
    'source', 'location', 'notes', 'status', 'tags', 'lead_score', 'raw_data',
    'unsubscribe_token', 'unsubscribed_at', 'replied_at',
])]
class CrmContact extends Model
{
    protected function casts(): array
    {
        return [
            'is_sandbox' => 'boolean',
            'raw_data' => 'array',
            'tags' => 'array',
            'lead_score' => 'integer',
            'unsubscribed_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * Audit gap #7 (outbound webhooks — "crm_contact.created"). Hooked at
     * the model level since contacts are created from three separate
     * places (the API, Lead Finder, and the CRM dashboard's own form) —
     * one choke point beats duplicating a dispatch call in each.
     */
    protected static function booted(): void
    {
        static::created(function (CrmContact $contact) {
            // Sandbox API test data never triggers real webhooks.
            if ($contact->is_sandbox) {
                return;
            }

            app(WebhookDispatcher::class)->dispatch($contact->user, 'crm_contact.created', [
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'company' => $contact->company,
                'source' => $contact->source,
                'status' => $contact->status,
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(CrmEmailSend::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(CrmList::class, 'crm_contact_list')->withPivot('added_at');
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CrmCampaignRecipient::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CrmReply::class);
    }

    /**
     * First word of the name — the {first_name} merge field. Business
     * listings (Google Maps imports) have a company name instead, so it
     * falls back to "there" ("Hi there,") rather than "Hi Joe's,".
     */
    public function firstName(): string
    {
        $name = trim((string) $this->name);

        if ($name === '' || ($this->company && strcasecmp($name, (string) $this->company) === 0)) {
            return 'there';
        }

        return (string) str($name)->before(' ');
    }

    /**
     * Best-effort city for the {city} merge field: the contact's location
     * up to its first comma ("Lagos, Nigeria" → "Lagos"), skipping a
     * leading street address.
     */
    public function city(): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $this->location))));

        if ($parts === []) {
            return '';
        }

        $city = preg_match('/\d/', $parts[0]) && isset($parts[1]) ? $parts[1] : $parts[0];

        return trim((string) preg_replace('/\s+[A-Z0-9][\dA-Z\s-]*\d[\dA-Z\s-]*$/', '', $city));
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    /**
     * Lazily generated and persisted on first use, mirroring
     * User::referralCode() — most contacts are never emailed, so most rows
     * never need one.
     */
    public function unsubscribeToken(): string
    {
        if ($this->unsubscribe_token) {
            return $this->unsubscribe_token;
        }

        do {
            $token = Str::random(32);
        } while (static::where('unsubscribe_token', $token)->exists());

        $this->update(['unsubscribe_token' => $token]);

        return $token;
    }
}
