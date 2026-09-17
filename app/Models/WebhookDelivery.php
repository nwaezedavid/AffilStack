<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per attempted delivery of an event to a WebhookEndpoint — created
 * once when the event fires, then updated in place by SendWebhookDelivery
 * as it retries, so a user can see exactly what was sent, what came back,
 * and how many attempts it took.
 */
#[Fillable(['webhook_endpoint_id', 'event', 'payload', 'status', 'attempts', 'response_status', 'response_body', 'delivered_at'])]
class WebhookDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
