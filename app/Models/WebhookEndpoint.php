<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Audit gap #7 — a user-registered outbound webhook endpoint, subscribed to
 * whichever event names (config('webhooks.events')) they choose. See
 * App\Services\Webhooks\WebhookDispatcher for how an event finds its way
 * here, and WebhookDelivery for the per-attempt audit trail.
 */
#[Fillable(['user_id', 'url', 'secret', 'events', 'is_active', 'last_triggered_at'])]
class WebhookEndpoint extends Model
{
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }
}
