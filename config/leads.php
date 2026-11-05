<?php

/**
 * B2B lead finding — see App\Services\Leads\LeadFinder.
 */
return [
    // Leads one run may find, and the choices offered on the product page.
    'run_sizes' => [10, 25, 50, 100],
    'default_run_size' => 25,

    // Hard ceiling per account per day, whatever the plan or credit balance,
    // so a stuck agent can never burn through a provider's quota.
    'daily_cap' => 500,

    // Company websites fetched per run when looking for public contact emails.
    'website_fetch_timeout' => 8,
    'website_pages' => ['', '/contact', '/contact-us', '/about'],

    'apollo' => [
        'base_url' => 'https://api.apollo.io/api/v1',
        'timeout' => 20,
    ],

    'hunter' => [
        'base_url' => 'https://api.hunter.io/v2',
        'timeout' => 20,
    ],

    // Personal mailbox providers. Paid providers' results with these are
    // dropped (business emails only); a free-mail address a business
    // publishes on its own website is kept, since that's its public contact.
    'free_mail_domains' => [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'ymail.com', 'hotmail.com', 'hotmail.co.uk',
        'outlook.com', 'live.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com', 'mac.com', 'proton.me',
        'protonmail.com', 'gmx.com', 'gmx.net', 'mail.com', 'yandex.com', 'zoho.com',
    ],

    // Addresses that are never a real contact (image names, site builders, trackers).
    'ignored_email_patterns' => [
        '/\.(png|jpe?g|gif|webp|svg)$/i',
        '/@(sentry|wixpress|example|domain|email|yourdomain|sentry-next)\./i',
        '/^(noreply|no-reply|donotreply|do-not-reply)@/i',
    ],
];
