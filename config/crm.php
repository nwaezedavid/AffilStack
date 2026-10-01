<?php

/*
 * CRM campaigns, sending limits and reply detection (agent 08). Credit costs
 * for the CRM's AI actions live here rather than config/credits.php so the
 * whole feature is tuned from one file.
 */
return [

    'credits' => [
        // One AI-written campaign (subject + body + optional follow-ups).
        'campaign_generate' => (int) env('CRM_CREDITS_CAMPAIGN', 8),
        // One AI opening line per contact, generated at send time — optional.
        'personal_opening' => (int) env('CRM_CREDITS_PERSONAL_OPENING', 1),
        // One AI-drafted reply in the Inbox.
        'reply_draft' => (int) env('CRM_CREDITS_REPLY_DRAFT', 3),
    ],

    /*
     * Daily sending caps per provider. The user can lower (never raise) theirs
     * on the Email Sending page. Gmail consumer accounts allow ~500/day;
     * we stay safely below it.
     */
    'daily_caps' => [
        'gmail' => (int) env('CRM_DAILY_CAP_GMAIL', 400),
        'smtp' => (int) env('CRM_DAILY_CAP_SMTP', 300),
        'platform' => (int) env('CRM_DAILY_CAP_PLATFORM', 50),
    ],

    // Every campaign email must leave through the user's own verified sender.
    'require_connection' => (bool) env('CRM_REQUIRE_CONNECTION', true),

    'sending' => [
        // Max emails one queue run / scheduler tick sends for one campaign.
        'batch_size' => (int) env('CRM_SEND_BATCH', 25),
        // Pause between two sends (ms) — keeps providers from rate-limiting.
        'pause_ms' => (int) env('CRM_SEND_PAUSE_MS', 1200),
        // Hard wall-clock budget of one run (seconds) — shared hosting kills ~60s.
        'time_budget' => (int) env('CRM_SEND_TIME_BUDGET', 40),
    ],

    'replies' => [
        // A reply from a contact's address counts if we emailed them within this many days.
        'sender_match_days' => (int) env('CRM_REPLY_MATCH_DAYS', 45),
        // Poller budget per scheduler tick (seconds) and per-mailbox message cap.
        'poll_time_budget' => (int) env('CRM_POLL_TIME_BUDGET', 45),
        'max_messages_per_mailbox' => (int) env('CRM_POLL_MAX_MESSAGES', 50),
        // First poll of a new mailbox looks back this many days.
        'initial_lookback_days' => (int) env('CRM_POLL_LOOKBACK_DAYS', 3),
        'imap_timeout' => (int) env('CRM_IMAP_TIMEOUT', 15),
        // Max bytes of a body part fetched per message.
        'max_body_bytes' => 65536,
    ],

    'list_colors' => ['brand', 'emerald', 'violet', 'amber', 'rose', 'teal', 'sky', 'gold'],

    /*
     * Mail-server presets for the Email Sending page — prefill host, port and
     * encryption for SMTP and IMAP.
     */
    'presets' => [
        'hostinger' => [
            'label' => 'Hostinger Email',
            'smtp' => ['host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap' => ['host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl'],
            'help' => 'Use your full mailbox address and its mailbox password from hPanel → Emails.',
            'help_url' => 'https://support.hostinger.com/en/articles/1583288-how-to-set-up-hostinger-email-on-email-clients',
        ],
        'gmail' => [
            'label' => 'Gmail / Google Workspace',
            'smtp' => ['host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap' => ['host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl'],
            'help' => 'Turn on 2-Step Verification, then create a 16-character App Password — your normal Gmail password will be rejected.',
            'help_url' => 'https://myaccount.google.com/apppasswords',
        ],
        'outlook' => [
            'label' => 'Outlook / Microsoft 365',
            'smtp' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'],
            'imap' => ['host' => 'outlook.office365.com', 'port' => 993, 'encryption' => 'ssl'],
            'help' => 'Your Microsoft 365 admin may need to enable "Authenticated SMTP" for this mailbox. Use an app password if MFA is on.',
            'help_url' => 'https://learn.microsoft.com/en-us/exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission',
        ],
        'zoho' => [
            'label' => 'Zoho Mail',
            'smtp' => ['host' => 'smtp.zoho.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap' => ['host' => 'imap.zoho.com', 'port' => 993, 'encryption' => 'ssl'],
            'help' => 'Enable IMAP access in Zoho Mail settings. With 2FA on, generate an application-specific password.',
            'help_url' => 'https://www.zoho.com/mail/help/zoho-smtp.html',
        ],
        'yahoo' => [
            'label' => 'Yahoo Mail',
            'smtp' => ['host' => 'smtp.mail.yahoo.com', 'port' => 465, 'encryption' => 'ssl'],
            'imap' => ['host' => 'imap.mail.yahoo.com', 'port' => 993, 'encryption' => 'ssl'],
            'help' => 'Yahoo requires an app password: Account security → Generate app password.',
            'help_url' => 'https://help.yahoo.com/kb/SLN15241.html',
        ],
        'custom' => [
            'label' => 'Other / custom server',
            'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls'],
            'imap' => ['host' => '', 'port' => 993, 'encryption' => 'ssl'],
            'help' => 'Your host or email provider lists these settings in their help centre, usually under "SMTP / IMAP settings".',
            'help_url' => null,
        ],
    ],
];
