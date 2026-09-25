<?php

namespace App\Models;

use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'is_sandbox', 'name', 'company', 'title', 'email', 'phone', 'website',
    'source', 'location', 'notes', 'status', 'raw_data',
    'unsubscribe_token', 'unsubscribed_at',
])]
class CrmContact extends Model
{
    protected function casts(): array
    {
        return [
            'is_sandbox' => 'boolean',
            'raw_data' => 'array',
            'unsubscribed_at' => 'datetime',
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
