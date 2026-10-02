<?php

// The affiliate Marketing Kit (dashboard.referrals → Banners / Email swipes /
// Social posts / Text links & brand assets / Guidelines). Banner designs are
// drawn in code (App\Services\Referrals\MarketingKit\BannerDesigner) so every
// size stays pixel-crisp as SVG and renders to PNG with the bundled Onest
// font; admins can add their own PNG/JPG/GIF creatives on top via Filament
// (Billing → Affiliate Banners), and those take priority in the gallery.
return [

    // Standard IAB display sizes plus the three social formats. "group" drives
    // the size filter in the gallery.
    'sizes' => [
        '728x90' => ['width' => 728, 'height' => 90, 'label' => 'Leaderboard', 'group' => 'leaderboard'],
        '970x250' => ['width' => 970, 'height' => 250, 'label' => 'Billboard', 'group' => 'leaderboard'],
        '300x250' => ['width' => 300, 'height' => 250, 'label' => 'Medium rectangle', 'group' => 'rectangle'],
        '336x280' => ['width' => 336, 'height' => 280, 'label' => 'Large rectangle', 'group' => 'rectangle'],
        '300x600' => ['width' => 300, 'height' => 600, 'label' => 'Half page', 'group' => 'skyscraper'],
        '160x600' => ['width' => 160, 'height' => 600, 'label' => 'Wide skyscraper', 'group' => 'skyscraper'],
        '320x50' => ['width' => 320, 'height' => 50, 'label' => 'Mobile banner', 'group' => 'mobile'],
        '320x100' => ['width' => 320, 'height' => 100, 'label' => 'Large mobile banner', 'group' => 'mobile'],
        '1080x1080' => ['width' => 1080, 'height' => 1080, 'label' => 'Square post (Instagram, Facebook, LinkedIn)', 'group' => 'social'],
        '1200x628' => ['width' => 1200, 'height' => 628, 'label' => 'Link post (Facebook, LinkedIn, X)', 'group' => 'social'],
        '1080x1920' => ['width' => 1080, 'height' => 1920, 'label' => 'Story / Reel / Status', 'group' => 'social'],
    ],

    'size_groups' => [
        'leaderboard' => 'Leaderboards',
        'rectangle' => 'Rectangles',
        'skyscraper' => 'Skyscrapers',
        'mobile' => 'Mobile',
        'social' => 'Social',
    ],

    // Built-in designs. Copy lives here so marketing can tweak a headline
    // without touching the drawing code; "short" variants are used where the
    // long one can't fit (strips and skyscrapers).
    'designs' => [
        'all-in-one' => [
            'name' => 'One stack',
            'theme' => 'navy',
            'visual' => 'stack',
            'headline' => 'Your whole affiliate business. One stack.',
            'headline_short' => 'Your affiliate business, one stack',
            'sub' => 'Research products, find buyers and launch AI campaigns in minutes.',
            'sub_short' => 'Research · Buyers · AI campaigns',
            'cta' => 'Get started',
        ],
        'commissions' => [
            'name' => 'Earn more',
            'theme' => 'blue',
            'visual' => 'bars',
            'headline' => 'Earn more affiliate commissions with AI',
            'headline_short' => 'Earn more commissions with AI',
            'sub' => 'Find winning offers, reach real buyers and post ready-made campaigns.',
            'sub_short' => 'Winning offers. Real buyers.',
            'cta' => 'Start earning',
        ],
        'research' => [
            'name' => 'Stop guessing',
            'theme' => 'light',
            'visual' => 'search',
            'headline' => 'Stop guessing which products will sell.',
            'headline_short' => 'Stop guessing what sells',
            'sub' => 'AffilStack researches the offer, finds the buyers and writes the campaign.',
            'sub_short' => 'Research → buyers → campaign',
            'cta' => 'Try AffilStack',
        ],
        'workflow' => [
            'name' => 'In minutes',
            'theme' => 'brand',
            'visual' => 'flow',
            'headline' => 'Research. Content. Sales. In minutes.',
            'headline_short' => 'Research. Content. Sales.',
            'sub' => 'From product research to paid commissions in one AI-powered workflow.',
            'sub_short' => 'One AI-powered workflow',
            'cta' => 'See how it works',
        ],
        'stack-replace' => [
            'name' => 'Replace your stack',
            'theme' => 'gold',
            'visual' => 'checklist',
            'headline' => 'One tool replaces your whole affiliate stack.',
            'headline_short' => 'One tool. Whole stack.',
            'sub' => 'Research, CRM, AI content and tracking without juggling subscriptions.',
            'sub_short' => 'Research · CRM · AI content',
            'cta' => 'Replace your stack',
        ],
    ],

    // Steps / checklist labels the "flow" and "checklist" visuals draw.
    'flow_steps' => ['Research', 'Find buyers', 'AI content', 'Earn'],
    'checklist_items' => ['Product research', 'Buyer leads & CRM', 'AI blog, social & video', 'Click & sale tracking'],

    // Banner impressions are counted once per viewer (hashed IP + user agent)
    // per banner within this window — enough to stop refresh-spam inflating
    // the numbers without storing any visitor data.
    'impression_dedupe_minutes' => (int) env('AFFILIATE_BANNER_IMPRESSION_DEDUPE_MINUTES', 30),

    // How long browsers/CDNs may reuse a /banners/* image. URLs are stable
    // (pasted into other people's sites), so this is deliberately long but not
    // "immutable" — a redesign still reaches everyone within the window.
    'image_cache_seconds' => 60 * 60 * 24 * 30,

    // Product descriptions offered in the "Text links & brand assets" tab.
    'descriptions' => [
        'tagline' => 'Research → Content → Sales, in minutes.',
        'short' => 'AffilStack is the all-in-one AI platform for affiliate marketers: research products, find buyers, and launch AI-written campaigns from one dashboard.',
        'long' => 'AffilStack replaces the pile of tools most affiliate marketers juggle. Paste a product link or pick a niche and it researches the offer, finds the buyers (with a built-in CRM and local lead finder), and writes ready-to-publish campaigns: blog articles, social posts, email sequences and UGC-style videos. Every click and sale is tracked in one place, so you can see what earns and double down on it. Built for solo affiliates, content creators and small teams, including African and diaspora entrepreneurs who want a professional stack without stitching together a dozen subscriptions.',
    ],

    // Platforms a social swipe can target (Filament select + share buttons).
    'social_platforms' => [
        'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp status',
        'tiktok' => 'TikTok caption',
    ],
];
