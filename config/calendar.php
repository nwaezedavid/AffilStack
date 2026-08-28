<?php

/**
 * Content calendar & reminders (Phase 3, item 5).
 *
 * AffiliStack never posts on the user's behalf (LinkedIn's API forbids
 * third-party auto-posting without a Marketing Partner agreement, and the
 * other channels are copy-paste by design too — see the plan's platform
 * limits section) so there is no real "auto-scheduler" that fires posts.
 * What this actually is: one view of every piece of publishable content
 * across every channel with a status and a target date, plus two kinds of
 * reminder computed from real data already in the system rather than an
 * invented publishing pipeline.
 */
return [
    // Modules that represent a single publishable asset — these are what
    // show up as rows in the calendar. Research, keyword lists, angle
    // ideas, and DM/email sequences aren't "one thing you publish on a
    // date" so they're excluded from the list (though linkedin_dm_sequence
    // still uses published_at — see reminders below).
    'publishable_modules' => [
        'blog_article',
        'linkedin_post',
        'linkedin_article',
        'youtube_script',
        'ugc_content',
        'x_thread',
        'tiktok_video',
        'pinterest_pin',
    ],

    // Days after a blog article's published_at before it's worth a content
    // refresh (updating stats/examples to protect its SEO ranking).
    'blog_refresh_days' => (int) env('CONTENT_BLOG_REFRESH_DAYS', 90),

    // A reminder becomes "upcoming" this many days before it's actually
    // due, so the user has time to act rather than being surprised same-day.
    'reminder_lookahead_days' => (int) env('CONTENT_REMINDER_LOOKAHEAD_DAYS', 3),
];
