<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'flutterwave' => [
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        // Set in the Flutterwave dashboard under Settings > Webhooks — NOT your API secret key.
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
    ],

    'stripe' => [
        'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        // Set in the Stripe dashboard under Developers > Webhooks (signing secret, starts "whsec_").
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paystack' => [
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        // Admin-settable USD->NGN rate used to convert a plan's USD price
        // into the kobo amount charged — Paystack has no native USD support
        // for Nigerian cards, so this must be kept current from the admin
        // Payment Gateways page rather than a live FX API this app doesn't
        // otherwise depend on.
        'usd_to_ngn_rate' => env('PAYSTACK_USD_TO_NGN_RATE', 1600),
    ],

    'paypal' => [
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.paypal.com'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        // From the PayPal Developer Dashboard app's Webhooks tab — needed to
        // call /v1/notifications/verify-webhook-signature.
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
    ],

    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'google_oauth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

];
