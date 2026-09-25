<?php

// The API usage prepay wallet's pricing — completely separate from
// config('credits.costs'), which prices dashboard AI generations against a
// subscription's monthly credit allotment. Calling the API is billed on top
// of whatever the underlying action already costs internally: an
// API-triggered offer research still spends the normal 5 research credits
// from the caller's plan exactly as a dashboard-triggered one would, AND
// draws this separate real-money fee from their wallet for the API call
// itself. See MeterApiUsage and ApiWalletManager, and the pricing
// recommendation doc for the reasoning behind these figures.
return [

    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------|
    | Per-call pricing, by route name
    |--------------------------------------------------------------------|
    |
    | A route not listed here is metered at default_cost_cents (0 — free).
    | Every current /v1/* read endpoint (GET /me, /offers, /generations,
    | /crm-contacts, /referrals/summary) stays free: they cost this app
    | nothing to serve. Only api.v1.offers.store triggers real AI spend
    | today. Priced at roughly 15c per dashboard "credit" the same action
    | would cost (config('credits.costs.research') = 5 credits) — a
    | deliberate premium over the ~8-9.5c/credit blended rate this app's own
    | CreditPackage top-ups sell at, since pay-per-call has no subscription
    | commitment behind it and must independently absorb card-processing
    | fees the wallet amortizes across many calls. See the pricing
    | recommendation doc for the full comparison table.
    |
    */
    'costs' => [
        'api.v1.offers.store' => 75, // $0.75 — mirrors research's 5-credit dashboard cost
    ],

    'default_cost_cents' => 0,

    // Crossing at/below this balance fires the 'api_wallet.low_balance'
    // webhook event once (see ApiWalletManager::charge()) — not a hard
    // floor; a call still succeeds down to $0.00 exactly.
    'low_balance_threshold_cents' => 500,

    // Sanity bounds the dashboard wallet settings form enforces on a user's
    // own auto-recharge threshold/amount — see ApiWalletController.
    'auto_recharge' => [
        'min_threshold_cents' => 100,     // $1.00
        'max_threshold_cents' => 10000,   // $100.00
        'min_amount_cents' => 1000,       // $10.00 — keeps card-processing fees proportionate
        'max_amount_cents' => 50000,      // $500.00
    ],

    // Preset top-up amounts shown on the wallet page — a plain "top up $X,
    // get $X" with no bonus tiers (see the pricing doc for why: this app's
    // own API billing should read as transparent metered usage, the same
    // trust model OpenAI/Anthropic's own API billing relies on, not a
    // packaged-credits upsell like CreditPackage already is for dashboard
    // credits).
    'topup_presets_cents' => [1000, 2500, 10000],

    'min_topup_cents' => 1000, // $10.00 minimum, same reasoning as min_amount_cents above

    /*
    |--------------------------------------------------------------------|
    | Plan-aware rate limits (API roadmap item #3)
    |--------------------------------------------------------------------|
    |
    | Requests per minute per token, resolved from the calling token's
    | owner's billed plan (see AppServiceProvider's 'api' RateLimiter and
    | routes/api.php's throttle:api). 'default' covers a token with no
    | active subscription behind it (e.g. a trialing/canceled account) —
    | previously every token got the old flat 60/min regardless of plan.
    |
    */
    'rate_limits' => [
        'starter' => 60,
        'growth' => 60,
        'pro' => 120,
        'business' => 300,
        'agency' => 300,
        'default' => 60,
    ],

    // API roadmap item #6 — max contacts accepted in one POST
    // /crm-contacts/bulk call. High enough for a real CRM migration/import,
    // low enough that one request can't tie up a worker or blow past a
    // plan's contact limit unnoticed.
    'bulk_max_items' => 100,

];
