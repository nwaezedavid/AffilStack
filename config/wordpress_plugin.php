<?php

// The AffilStack Connector WordPress plugin (resources/wordpress/
// affilstack-connector). Bump "version" with every plugin change: connected
// sites see the update in WordPress and install it with one click.
return [
    'slug' => 'affilstack-connector',
    'version' => '1.0.0',
    'requires_wp' => '6.0',
    'tested_wp' => '6.8',
    'requires_php' => '7.4',
    'changelog' => [
        '1.0.0' => 'First release: one-click connect, articles arrive as drafts with images, SEO fields, categories, tags, FAQ schema and tracked links.',
    ],

    // How long a "connect this site" approval waits for the plugin to
    // collect its secret.
    'connect_code_minutes' => 10,

    // Signed requests older or newer than this are refused (replay guard).
    'signature_window_seconds' => 300,
];
