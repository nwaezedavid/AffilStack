<?php

// Credit cost per AI generation module. Keep these in sync with the pricing
// plan document — they are the single source of truth for how many credits
// each action consumes across every plan tier.
return [
    'costs' => [
        'research' => 5,
        'blog_article' => 15,
        'linkedin_keywords' => 3,
        'linkedin_dm_sequence' => 8,
        'linkedin_post' => 5,
        'linkedin_article' => 12,
        'youtube_script' => 15,
        'youtube_metadata' => 10,
        'ugc_angles' => 6,
        'ugc_content' => 12,
        // A real HeyGen-rendered video (avatar_iii engine, 720p, ~45-60s
        // spoken script) costs AffilStack roughly $0.75-$1 — 40 credits is
        // sized for a healthy multiple of that at every plan's per-credit
        // rate, not just a break-even number. Adjust here if real usage
        // shows scripts running longer than expected.
        'ugc_video' => 40,
        'x_thread' => 8,
        'tiktok_video' => 12,
        'pinterest_pin' => 8,
        'email_nurture' => 10,
        'competitor_angles' => 6,
        'localization' => 8,
    ],
];
