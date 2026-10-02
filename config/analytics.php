<?php

// Agent 04: cookie consent + first-party behaviour analytics. Everything an
// admin can change at runtime (banner copy, policy version, retention) lives
// in SiteSetting and is edited from Analytics → Cookie Consent; these are the
// code-level defaults and the fixed technical limits behind them. See
// App\Support\Analytics\ConsentSettings and App\Services\Analytics\*.
return [

    'cookies' => [
        // Strictly necessary: remembers the visitor's own consent choice.
        'consent' => 'afs_consent',
        'consent_days' => (int) env('ANALYTICS_CONSENT_COOKIE_DAYS', 180),

        // Analytics (only ever set after analytics consent): a random,
        // anonymous visitor id. 13 months is the ceiling most EU regulators
        // (e.g. CNIL) accept for audience-measurement cookies.
        'visitor' => 'afs_vid',
        'visitor_days' => 395,
    ],

    // A new session starts after this many minutes without a page view or
    // event (Google Analytics' default), or when a new campaign arrives.
    'session_timeout_minutes' => 30,

    // Default for Analytics → Cookie Consent → "Keep analytics data for".
    'retention_months' => (int) env('ANALYTICS_RETENTION_MONTHS', 13),

    // Default policy version — bump it in the admin to ask everyone again.
    'policy_version' => '2026-10',

    // Lifecycle stage thresholds (days).
    'lifecycle' => [
        'new_customer_days' => 7,
        'active_within_days' => 14,
        'engaged_score' => 25,
        'engaged_sessions' => 3,
    ],

    // Engagement score (0–100) over the last 30 days: each signal is
    // weighted and capped so no single behaviour dominates.
    'engagement' => [
        'window_days' => 30,
        'active_day_points' => 4, 'active_day_cap' => 40,
        'page_view_points' => 0.5, 'page_view_cap' => 15,
        'feature_event_points' => 2, 'feature_event_cap' => 30,
        'engaged_minute_points' => 0.5, 'engaged_minute_cap' => 15,
        'highly_engaged_score' => 60,
    ],

    // Beacon abuse limits (per IP, per minute).
    'rate_limits' => [
        'events_per_minute' => 60,
        'consent_per_minute' => 20,
    ],

    // Custom events from window.affilstackTrack(): names and property keys
    // are restricted to snake_case, values are clamped, and anything that
    // looks like contact details is dropped before it is stored.
    'events' => [
        'max_properties' => 10,
        'max_value_length' => 200,
        'blocked_property_keys' => ['email', 'e_mail', 'mail', 'phone', 'mobile', 'tel', 'name', 'first_name', 'last_name', 'full_name', 'password', 'pass', 'token', 'address', 'card', 'iban'],
    ],

    // Human labels for the automatic and server-side events, used by the
    // admin insights and journeys pages.
    'event_labels' => [
        'cta_click' => 'Clicked a call-to-action',
        'outbound_click' => 'Clicked an external link',
        'pricing_viewed' => 'Viewed pricing',
        'plan_selected' => 'Selected a plan',
        'checkout_started' => 'Started checkout',
        'signup_completed' => 'Completed signup (paid)',
        'purchase' => 'Payment completed',
        'login' => 'Logged in',
        'research_started' => 'Started product research',
        'content_generated' => 'Generated content',
        'campaign_sent' => 'Sent an email campaign',
        'extension_used' => 'Used the browser extension',
    ],

    // Server-side "dashboard feature use" events — the Top features chart.
    'feature_events' => ['research_started', 'content_generated', 'campaign_sent', 'extension_used'],

    // API routes that count as "used the browser extension" (agent 09).
    'extension_routes' => [
        'api.clips.store' => 'research_clip',
        'api.extension.crm.contacts.store' => 'maps_to_crm',
        'api.extension.linkedin.message' => 'linkedin_message',
        'api.extension.linkedin.reply' => 'linkedin_reply',
    ],
];
