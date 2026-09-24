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
        'linkedin_reply_draft' => 3,
        'youtube_script' => 15,
        'youtube_metadata' => 10,
        'ugc_angles' => 6,
        'ugc_content' => 12,
        // A real HeyGen-rendered video (avatar_iii engine, 720p) costs
        // AffilStack $0.05/second of rendered video (verified against
        // HeyGen's own API pricing) — and UgcService's own video_script
        // prompt targets "roughly 120-170 words (about 45-60 seconds
        // spoken)", so a real video costs $2.25-$3.00, not the $0.75-$1
        // this used to assume. 100 credits keeps this comfortably
        // profitable (47-76% margin depending on plan and script length,
        // vs the old 40-credit price's -13% to +41%) — see the pricing/AI
        // cost analysis this was corrected alongside for the full math.
        // Adjust here if real usage shows scripts running longer than
        // UgcService's own prompt asks for.
        'ugc_video' => 100,
        'x_thread' => 8,
        'tiktok_video' => 12,
        'pinterest_pin' => 8,
        'email_nurture' => 10,
        'competitor_angles' => 6,
        'localization' => 8,
        // Task #7 (Intelligence Centre): one AI self-assessment run over
        // the account's own activity/plan-usage numbers — priced like a
        // single research/analysis call, not a content-generation module.
        'intelligence_centre' => 10,
    ],
];
