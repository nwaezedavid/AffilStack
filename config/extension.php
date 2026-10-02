<?php

/**
 * Browser capture extension (Phase 3, item 11). The extension is a plain
 * bearer-token API client — no Sanctum dependency added; see ApiToken and
 * App\Http\Middleware\ApiTokenAuth for the (Sanctum-equivalent, hand-rolled)
 * token scheme. This file holds the shared option list for a captured
 * clip's page type, used by both the API validation and the dashboard UI.
 */
return [
    'page_types' => [
        'product_page' => 'Product page',
        'competitor_ad' => 'Competitor ad',
        'linkedin_post' => 'LinkedIn post',
        'other' => 'Other',
    ],

    // The extension's Chrome Web Store listing, once published. When set,
    // the dashboard offers a one-click "Add to Chrome" instead of the
    // developer-mode install steps.
    'store_url' => env('CHROME_EXTENSION_STORE_URL'),

    // Version of resources/browser-extension/manifest.json — shown on the
    // dashboard page so people know which build they're installing.
    'version' => '1.2.0',

    // Most businesses one "Add to CRM" click can send from a Maps results list.
    'max_contacts_per_request' => (int) env('EXTENSION_MAX_CONTACTS_PER_REQUEST', 50),

    /*
     * LinkedIn caps a connection-request note at 200 characters on free
     * accounts (300 on Premium/Sales Navigator). The generated note is kept
     * within the free limit so it fits every account without editing.
     */
    'linkedin_connection_note_limit' => (int) env('EXTENSION_LINKEDIN_NOTE_LIMIT', 200),

    // How many recent messages of a conversation the reply assistant reads.
    'reply_max_messages' => 20,
];
