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
];
