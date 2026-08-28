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
        'x_thread' => 8,
        'tiktok_video' => 12,
        'email_nurture' => 10,
    ],
];
