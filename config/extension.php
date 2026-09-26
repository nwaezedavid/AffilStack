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
];
