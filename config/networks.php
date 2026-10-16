<?php

/**
 * Feature 5: the affiliate networks customers can connect. Each entry has
 * the fields the connect form asks for (in the network's own words, with
 * where to find them), the steps shown beside the form, and the addresses
 * used for "Apply on {network}" and "Open {network}".
 *
 * None of these networks offers "Sign in with…" for affiliates, so
 * connecting means pasting keys the customer creates in their network
 * account; Digistore24 alone has an approve-in-browser flow, used when an
 * admin has added AffilStack's Digistore24 developer key (Admin →
 * Affiliate Networks).
 */
return [
    // First sync looks back this far; later syncs re-read this many days
    // so status changes (pending → approved → paid) are picked up.
    'initial_sync_days' => 90,
    'resync_days' => 60,

    // "Sync now" can't be pressed more often than this.
    'manual_sync_cooldown_minutes' => 10,

    // Available (not yet joined) programs kept per connection, so a huge
    // marketplace doesn't flood the database.
    'max_available_programs' => 1000,

    'networks' => [
        'impact' => [
            'label' => 'Impact.com',
            'affiliate_network_name' => 'Impact.com',
            'tagline' => 'Brands and SaaS programs on impact.com',
            'dashboard_url' => 'https://app.impact.com',
            'fields' => [
                'account_sid' => ['label' => 'Account SID', 'help' => 'Starts with "IR". Shown on the API page in Impact.', 'secret' => false],
                'auth_token' => ['label' => 'Auth Token', 'help' => 'Click "Create access token" on the same page (read-only is enough).', 'secret' => true],
            ],
            'steps' => [
                'Sign in to app.impact.com.',
                'Open your profile menu → Settings → Technical → API.',
                'Copy your Account SID, then create an access token with read access.',
                'Paste both here and click Connect.',
            ],
            'marketplace' => false,
        ],
        'partnerstack' => [
            'label' => 'PartnerStack',
            'affiliate_network_name' => 'PartnerStack',
            'tagline' => 'SaaS partner programs',
            'dashboard_url' => 'https://dash.partnerstack.com',
            'fields' => [
                'api_key' => ['label' => 'API key', 'help' => 'Partner dashboard → Settings → API → Reveal.', 'secret' => true],
            ],
            'steps' => [
                'Sign in to your PartnerStack partner dashboard.',
                'Go to Settings → API and click Reveal.',
                'Copy the API key, paste it here and click Connect.',
            ],
            'marketplace' => true,
        ],
        'awin' => [
            'label' => 'Awin',
            'affiliate_network_name' => 'Awin',
            'tagline' => 'Retail brands, including former ShareASale programs',
            'dashboard_url' => 'https://ui.awin.com',
            'apply_url' => 'https://ui.awin.com/merchant-profile/{id}',
            'fields' => [
                'api_token' => ['label' => 'API token', 'help' => 'From ui.awin.com/awin-api ("API Credentials"). You need admin access to the account.', 'secret' => true],
                'publisher_id' => ['label' => 'Publisher ID (optional)', 'help' => 'Only if your login manages several publisher accounts. Leave blank to use the first.', 'secret' => false, 'optional' => true],
            ],
            'steps' => [
                'Sign in to ui.awin.com.',
                'Open ui.awin.com/awin-api (or your profile → API Credentials).',
                'Enter your password to show the token, then copy it.',
                'Paste it here and click Connect. Former ShareASale accounts work the same way.',
            ],
            'marketplace' => true,
        ],
        'cj' => [
            'label' => 'CJ Affiliate',
            'affiliate_network_name' => 'CJ Affiliate',
            'tagline' => 'Large retail and travel advertisers',
            'dashboard_url' => 'https://members.cj.com',
            'fields' => [
                'access_token' => ['label' => 'Personal access token', 'help' => 'developers.cj.com → Authentication → Personal Access Tokens → Create.', 'secret' => true],
                'publisher_cid' => ['label' => 'Company ID (CID)', 'help' => 'The number shown next to your account name at the top of members.cj.com.', 'secret' => false],
                'website_id' => ['label' => 'Website ID (PID)', 'help' => 'Account → Websites in CJ. Used to build your tracked links.', 'secret' => false],
            ],
            'steps' => [
                'Sign in at developers.cj.com with your CJ login.',
                'Open Authentication → Personal Access Tokens and create one.',
                'In members.cj.com, note your Company ID (top of the page) and your Website ID (Account → Websites).',
                'Paste all three here and click Connect.',
            ],
            'marketplace' => true,
        ],
        'clickbank' => [
            'label' => 'ClickBank',
            'affiliate_network_name' => 'ClickBank',
            'tagline' => 'Digital products and courses',
            'dashboard_url' => 'https://accounts.clickbank.com',
            'fields' => [
                'api_key' => ['label' => 'API key', 'help' => 'Starts with "API-". Create it with "Orders/Tickets API Read" permission.', 'secret' => true],
            ],
            'steps' => [
                'Sign in to your primary ClickBank account.',
                'Go to Settings → API Management → Create New API Key.',
                'Allow "Orders/Tickets API Read" for your affiliate account nickname.',
                'Paste the key (it starts with API-) here and click Connect.',
            ],
            'marketplace' => false,
        ],
        'digistore24' => [
            'label' => 'Digistore24',
            'affiliate_network_name' => 'Digistore24',
            'tagline' => 'Digital products, popular in Europe',
            'dashboard_url' => 'https://www.digistore24.com',
            'fields' => [
                'api_key' => ['label' => 'API key', 'help' => 'Settings → Account access → API keys → New API key (read-only is enough).', 'secret' => true],
            ],
            'steps' => [
                'Sign in to Digistore24.',
                'Go to Settings → Account access → API keys.',
                'Create a read-only API key, paste it here and click Connect.',
            ],
            'marketplace' => false,
        ],
    ],
];
