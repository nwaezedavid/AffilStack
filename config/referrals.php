<?php

// The built-in referral program. Kept in its own config file (like
// config/credits.php) so the commission rate and cookie lifetime are a
// single source of truth an admin can tune via env vars without touching
// code. See app/Services/Referrals/ReferralService.php.
return [
    // Share of each commissionable payment (first payment and every
    // renewal) paid out to the referring user, as a decimal fraction.
    'commission_rate' => (float) env('REFERRAL_COMMISSION_RATE', 0.20),

    // How long a referral click is remembered in the visitor's browser
    // before it stops attributing a signup to the referrer.
    'cookie_days' => (int) env('REFERRAL_COOKIE_DAYS', 60),

    'cookie_name' => 'affilstack_ref',

    // Where an unrecognized or expired /r/{code} link sends the visitor.
    'fallback_route' => 'registration.pricing',

    // An affiliate can't request a payout until their approved-but-unpaid
    // commission total clears this — keeps payouts worth the admin's time
    // to process individually. See ReferralPayoutService::requestPayout().
    'minimum_payout_cents' => (int) env('REFERRAL_MINIMUM_PAYOUT_CENTS', 5000),

    // Payout methods an affiliate can put on file (Partner Portal and the
    // customer Refer & Earn page). Each key's form fields live in
    // resources/views/partials/referrals/payout-form.blade.php.
    'payout_methods' => [
        'paystack_bank' => 'Nigerian bank account',
        'paypal' => 'PayPal',
        'usdt' => 'USDT (crypto wallet)',
    ],

    // Display names for every method a stored payout may carry, including
    // ones no longer offered for new payout details.
    'payout_method_labels' => [
        'paystack_bank' => 'Nigerian bank account',
        'paypal' => 'PayPal',
        'usdt' => 'USDT (crypto wallet)',
        'bank_transfer' => 'Bank transfer',
    ],

    // USDT networks a partner can be paid on, with the address format each
    // expects and the block explorer used to check a transaction hash.
    'usdt_networks' => [
        'trc20' => ['label' => 'TRON (TRC-20)', 'address' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/', 'hash' => '/^[A-Fa-f0-9]{64}$/', 'explorer' => 'https://tronscan.org/#/transaction/'],
        'bep20' => ['label' => 'BNB Smart Chain (BEP-20)', 'address' => '/^0x[A-Fa-f0-9]{40}$/', 'hash' => '/^0x[A-Fa-f0-9]{64}$/', 'explorer' => 'https://bscscan.com/tx/'],
    ],

    // Remembers which of a partner's campaign links a visitor clicked, so
    // sign-ups and earnings can be reported per campaign.
    'campaign_cookie_name' => 'affilstack_ref_campaign',

    // The affiliate program's own landing page — "anyone can sign-up to
    // become an affiliate without first becoming a user of the platform".
    // When AFFILIATE_SUBDOMAIN is set, affiliate.landing/affiliate.apply
    // are served from that subdomain (e.g. affiliate.affilstack.com);
    // otherwise they fall back to a plain /affiliate prefix on the main
    // domain so the feature works without real DNS in local dev — same
    // route names either way, so nothing else needs to know which mode is
    // active. See routes/web.php and AffiliateController.
    'landing_subdomain' => env('AFFILIATE_SUBDOMAIN'),

    // App routes an affiliate-only account (User::isAffiliateOnly()) may
    // still reach on the customer app. Every other app route sends them to
    // their Partner Portal (partner.dashboard) instead — see
    // App\Http\Middleware\RestrictAffiliateOnlyAccounts.
    'affiliate_only_allowed_routes' => [
        'logout',
    ],
];
