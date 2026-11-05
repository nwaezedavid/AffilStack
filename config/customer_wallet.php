<?php

/**
 * Customer wallet and invite-only trials. Amounts are in the minor unit
 * (cents) of the wallet's currency. Gateway credentials stay in the admin
 * area (Billing > Payment Gateways); nothing here is a secret.
 */
return [
    // Currency a new wallet opens in when the customer has no plan yet.
    'default_currency' => env('CUSTOMER_WALLET_CURRENCY', 'USD'),

    'min_topup_minor' => 500,
    'max_topup_minor' => 500000,

    'trial' => [
        // The small charge that saves the card when a trial starts. It is
        // kept as wallet balance and goes toward the first payment.
        'card_check_minor' => 100,

        // Gateways whose saved cards can be charged again later.
        'card_gateways' => ['stripe', 'paystack', 'flutterwave'],

        'reminder_days_before' => 2,

        // After a failed day-8 charge the subscription stays past due this
        // many days (so the customer can pay) before it expires.
        'past_due_grace_days' => 3,
    ],
];
