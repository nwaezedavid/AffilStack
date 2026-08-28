<?php

/**
 * Shared option lists for the swipe file library (Phase 3, item 9) — used
 * by both the Filament admin resource and the public browse page, so the
 * two never drift out of sync.
 */
return [
    'types' => [
        'hook' => 'Hook',
        'subject_line' => 'Email subject line',
        'thumbnail_style' => 'Thumbnail style',
    ],

    'niches' => [
        'health_wellness' => 'Health & Wellness',
        'finance_investing' => 'Finance & Investing',
        'saas_software' => 'SaaS & Software',
        'beauty_skincare' => 'Beauty & Skincare',
        'fitness' => 'Fitness',
        'home_diy' => 'Home & DIY',
        'tech_gadgets' => 'Tech & Gadgets',
        'general' => 'General / Any niche',
    ],
];
