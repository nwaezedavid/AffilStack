<?php

/**
 * Affiliate disclosure wording by jurisdiction and content length.
 *
 * "long" is used for written, scrollable content (blog articles, LinkedIn
 * articles, video descriptions) and is placed at the top so it's visible
 * before a reader has to scroll — regulators consistently want disclosure
 * "above the fold". "short" is used for caption/social-length content
 * (posts, tweets, captions) and is appended near any hashtags, which is
 * where audiences on those platforms expect to see it.
 *
 * This wording is a reasonable, widely-used default — not legal advice.
 * Affiliates operating under stricter house rules (a specific network's
 * required phrasing, a lawyer's guidance) should override it for their
 * own content.
 */
return [
    'default_country' => 'US',

    'countries' => [
        'US' => [
            'label' => 'United States (FTC)',
            'long' => "Disclosure: This content contains affiliate links. If you make a purchase through one of these links, I may earn a commission at no extra cost to you. I only recommend products I've researched and believe add value.",
            'short' => 'Affiliate link — I may earn a commission at no cost to you. #ad',
        ],
        'GB' => [
            'label' => 'United Kingdom (ASA/CMA)',
            'long' => 'Advertising Disclosure: This content contains affiliate links. I may receive a commission if you make a purchase through them, at no additional cost to you. This does not affect my recommendations.',
            'short' => 'Ad | Affiliate link — I may earn a commission if you buy.',
        ],
        'CA' => [
            'label' => 'Canada (Competition Bureau)',
            'long' => 'Disclosure: This content includes affiliate links. I may earn a commission on qualifying purchases made through these links, at no extra cost to you.',
            'short' => '#ad Affiliate link — I may earn a commission on purchases.',
        ],
        'AU' => [
            'label' => 'Australia (ACCC)',
            'long' => 'Disclosure: This content contains affiliate links, and I may earn a commission on purchases made through them, at no additional cost to you.',
            'short' => '#ad Affiliate link — a commission may be earned.',
        ],
        'EU' => [
            'label' => 'European Union (general)',
            'long' => 'Advertising Disclosure: This content contains affiliate/commercial links. I may receive a commission for purchases made through these links, at no extra cost to you.',
            'short' => '#ad #affiliate — a commission may apply.',
        ],
        'OTHER' => [
            'label' => 'Other / global (FTC-style default)',
            'long' => 'Disclosure: This content contains affiliate links. I may earn a commission on purchases made through them, at no extra cost to you.',
            'short' => '#ad — Affiliate link, I may earn a commission.',
        ],
    ],

    /*
     * Per-module placement. "format" picks which wording variant above;
     * "placement" is prepend (goes before the content, for long-form so
     * it's seen before scrolling) or append (goes after, for short-form
     * so it sits near hashtags/CTAs the way audiences expect).
     * Modules not listed here fall back to short/append.
     */
    'modules' => [
        'blog_article' => ['format' => 'long', 'placement' => 'prepend'],
        'linkedin_article' => ['format' => 'long', 'placement' => 'prepend'],
        'youtube_metadata' => ['format' => 'long', 'placement' => 'prepend'],
        'linkedin_post' => ['format' => 'short', 'placement' => 'append'],
        'x_thread' => ['format' => 'short', 'placement' => 'append'],
        'tiktok_video' => ['format' => 'short', 'placement' => 'append'],
        'ugc_content' => ['format' => 'short', 'placement' => 'append'],
        // Long-form because email tolerates (and reads better with) a full
        // sentence; appended like a P.S. signature line rather than
        // prepended, since opening a personal email with a disclosure
        // paragraph before ever saying hello would read as robotic.
        'email_nurture' => ['format' => 'long', 'placement' => 'append'],
    ],

    /*
     * Case-insensitive substrings that count as "already disclosed" —
     * covers AffiliStack's own inserted wording plus common phrasing a
     * user (or the AI) might already have written, so we never stack a
     * second disclosure on top of one that's already there.
     */
    'detection_phrases' => [
        'affiliate link',
        'affiliate links',
        '#ad',
        '#affiliate',
        'advertising disclosure',
        'disclosure:',
        'earn a commission',
        'i may receive a commission',
        'paid partnership',
        'sponsored content',
        'sponsored post',
    ],
];
