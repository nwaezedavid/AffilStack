<?php

namespace App\Services\Webhooks;

use App\Jobs\SendWebhookDelivery;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * Audit gap #7 — the single entry point every trigger (Generation, CrmContact,
 * Referral, Earning — see each model's booted()) calls to fan an event out
 * to whichever of $user's webhook endpoints subscribed to it. Creates one
 * WebhookDelivery per matching endpoint and queues its send, so the caller
 * never waits on a customer's server.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(?User $user, string $event, array $payload): void
    {
        if (! $user) {
            return;
        }

        $endpoints = WebhookEndpoint::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $event,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            SendWebhookDelivery::dispatch($delivery);
        }
    }
}
