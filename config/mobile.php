<?php

/**
 * The official AffilStack phone apps (Android and iOS). Nothing here is a
 * secret: the app is a "public" OAuth client (it can't keep a secret), so
 * it proves itself with PKCE and an allow-listed redirect address instead.
 * Store links, the minimum supported version and the announcement banner
 * are edited in admin (Website > Mobile Apps) — see App\Support\MobileApps.
 */
return [

    // What the app sends as client_id to /mobile/authorize and
    // /api/oauth/token.
    'client_id' => env('MOBILE_OAUTH_CLIENT_ID', 'affilstack-mobile'),

    // Where app.affilstack.com may send the one-time sign-in code. The
    // app registers the "affilstack" scheme. Extra addresses for
    // development builds can be added with MOBILE_OAUTH_EXTRA_REDIRECTS
    // (comma separated).
    'redirect_uris' => array_values(array_filter(array_merge(
        ['affilstack://auth/callback'],
        array_map('trim', explode(',', (string) env('MOBILE_OAUTH_EXTRA_REDIRECTS', ''))),
    ))),

    'authorization_code_minutes' => 5,

    'access_token_minutes' => 60,

    // A device that isn't opened for this long has to sign in again.
    'refresh_token_days' => 60,

    // Deleting the account from the app without typing a password (e.g. a
    // Google or Apple account that never had one) needs a sign-in this
    // recent.
    'recent_sign_in_minutes' => 15,

    // Requests per minute per signed-in device. Higher than the general
    // API's plan limits because one app screen loads several things at
    // once, exactly like a dashboard page.
    'rate_limit_per_minute' => 180,

    // Fallback minimum versions when admin hasn't set one.
    'default_min_version' => '1.0.0',

];
