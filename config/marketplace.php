<?php

/*
|--------------------------------------------------------------------------
| Developer portal & add-on marketplace (feature 7)
|--------------------------------------------------------------------------
|
| The money settings (revenue share, holding period) are defaults an admin
| can override in Marketplace → Marketplace settings.
|
*/

return [

    // developer.affilstack.com. Unset: the portal lives at /developers on
    // the app.
    'developer_domain' => env('DEVELOPER_SUBDOMAIN'),

    'revenue_share_percent' => 70,

    // Days a sale's earnings wait (the refund window) before they can be
    // paid out.
    'earnings_hold_days' => 14,

    // Days a customer keeps a paid add-on after a renewal payment fails.
    'grace_days' => 3,

    'min_price_cents' => 100,
    'max_price_cents' => 50000,

    'access_token_minutes' => 60,
    'refresh_token_days' => 90,
    'authorization_code_minutes' => 10,

    'types' => [
        'app' => ['label' => 'Connected app', 'description' => 'An app that connects to AffilStack accounts through the API and webhooks.'],
        'template_pack' => ['label' => 'Template pack', 'description' => 'Prompts, email sequences, posts and hooks customers install into their template library.'],
        'automation' => ['label' => 'Automation recipe', 'description' => 'A ready-made Zapier, Make or n8n workflow customers copy.'],
    ],

    'categories' => [
        'content' => 'Content creation',
        'outreach' => 'Outreach & CRM',
        'analytics' => 'Analytics & reporting',
        'publishing' => 'Publishing',
        'research' => 'Research',
        'productivity' => 'Productivity',
        'automation' => 'Automation',
        'other' => 'Other',
    ],

    /*
    | What a connected app can ask for. Each permission is shown to the
    | customer on the "Connect" screen in these words.
    */
    'scopes' => [
        'profile:read' => 'See your name, email address and plan',
        'offers:read' => 'See your products and their research',
        'offers:write' => 'Add products to research',
        'content:read' => 'See the content you generate',
        'crm:read' => 'See your CRM contacts and lists',
        'crm:write' => 'Add and update CRM contacts',
        'referrals:read' => 'See your referral stats',
        'earnings:read' => 'Get notified when an earning is recorded',
    ],

    // Webhook events an app can receive, and the permission each needs.
    'webhook_events' => [
        'generation.completed' => 'content:read',
        'crm_contact.created' => 'crm:read',
        'referral.converted' => 'referrals:read',
        'earning.recorded' => 'earnings:read',
    ],

    'template_kinds' => [
        'prompt' => 'Prompt',
        'email' => 'Email',
        'post' => 'Social post',
        'hook' => 'Hook',
        'script' => 'Video script',
    ],

    'automation_platforms' => [
        'zapier' => 'Zapier',
        'make' => 'Make',
        'n8n' => 'n8n',
    ],

];
