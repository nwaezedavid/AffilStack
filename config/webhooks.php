<?php

return [

    /*
    |--------------------------------------------------------------------|
    | Outbound webhook event catalog (audit gap #7)
    |--------------------------------------------------------------------|
    |
    | Every event a user can subscribe a webhook endpoint to, and the
    | model/service that fires it — see WebhookEndpoint::events and
    | App\Services\Webhooks\WebhookDispatcher.
    |
    */
    'events' => [
        'generation.completed' => 'Content generation completed',
        'crm_contact.created' => 'New CRM contact created',
        'referral.converted' => 'Referral converted',
        'earning.recorded' => 'Earning recorded',
    ],

    /*
    |--------------------------------------------------------------------|
    | Retry policy
    |--------------------------------------------------------------------|
    |
    | SendWebhookDelivery re-dispatches itself with a delay after a
    | non-2xx response or a connection failure, indexed by attempt number
    | (the first retry after the initial attempt is index 0). Once
    | max_attempts is reached the delivery is marked "failed" for good.
    |
    */
    'max_attempts' => 5,

    'retry_backoff_minutes' => [1, 5, 15, 60, 360],

];
