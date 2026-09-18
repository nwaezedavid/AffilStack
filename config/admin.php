<?php

return [

    /*
    |--------------------------------------------------------------------
    | Admin sub-account departments
    |--------------------------------------------------------------------
    |
    | The single source of truth for department-scoped admin access. Each
    | key becomes a Spatie permission named "department.{key}" (seeded by
    | RolesSeeder) and maps 1:1 onto an existing Filament navigation group.
    | A department-scoped sub-account (role "admin_sub") only sees the
    | resources/pages whose $department property matches one it's been
    | granted — see App\Filament\Concerns\ScopedToDepartment. Full admins
    | (role "admin" or "super-admin") always bypass this and see everything
    | — see User::isFullAdmin().
    |
    | Note: granting "ai_agents" lets a sub-account VIEW the Tom/Sam/Brain/
    | Tony dashboards, but every action with a real-world consequence
    | (approving a fix, publishing content, launching a paid ad campaign)
    | is separately gated to super-admin regardless of department access.
    | Granting "users_access" lets a sub-account manage customer accounts
    | (credits, suspension) but never touch anyone's roles — only
    | super-admin can do that, and admin sub-accounts are managed from
    | their own dedicated screen, not this one.
    |
    */
    'departments' => [
        'support' => 'Support — tickets, canned replies, contact messages, FAQ',
        'content' => 'Content — site pages, homepage features, swipe files, UGC video, TikTok/Instagram publishing settings',
        'billing' => 'Billing — plans, payment transactions, referral payouts, payment gateway settings, payout wallet, refund requests',
        'crm_oversight' => 'CRM Oversight — every user\'s CRM contacts',
        'ai_agents' => 'AI Agents — Tom/Sam/Brain/Tony dashboards (approving changes still requires super-admin)',
        'system' => 'System — scheduled task run history, API tokens, connections health check',
        'site' => 'Site — branding, SEO, Google login, Gmail-sending, and LinkedIn Connect settings',
        'users_access' => 'Users & Access — manage customer accounts (never roles or admin sub-accounts)',
    ],

];
