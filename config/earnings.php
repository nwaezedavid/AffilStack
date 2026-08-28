<?php

// Feature 2 (conversion & earnings tracker). Affiliate networks report
// conversions on their own dashboards, not ours — the only way to match a
// network's payout back to one of AffiliStack's own cloaked links is to
// pass the link's code through as a "sub ID" tracking parameter, which most
// networks (PartnerStack, Impact, ShareASale, and others) echo back
// verbatim in their reporting exports. See LinkController::redirect().
return [
    // The query parameter LinkController::redirect() appends to a cloaked
    // link's destination URL. Left as configurable because not every
    // network uses the same name for this — PartnerStack and Impact both
    // default to "subid", but some setups use "afftrack" or a custom name.
    // Never overwrites a same-named parameter the offer's own URL already has.
    'tracking_param' => env('EARNINGS_TRACKING_PARAM', 'subid'),

    // Shown in the network picker on the manual-entry and CSV-import forms.
    // "other" always exists so a network not listed here isn't a dead end.
    'networks' => [
        'partnerstack' => 'PartnerStack',
        'impact' => 'Impact',
        'shareasale' => 'ShareASale',
        'other' => 'Other',
    ],
];
