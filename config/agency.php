<?php

/**
 * Agency / team seats (Phase 3, item 10; extended by the Business/Team
 * tier design).
 *
 * A "seat" is a normal User row with `agency_owner_id` set — it logs in on
 * its own, but every credit it spends is billed to the owner
 * (User::billableUser(), enforced centrally in CreditManager). On every
 * plan except Business ("isolated" mode — see plans.seat_mode) it's
 * scoped to exactly one offer (`seat_offer_id`), matching the original
 * backlog's "draft only, no publish; one product only". On a Business
 * plan ("shared" mode) a seat instead sees and works every offer on the
 * account — see shared_only_routes below and User::hasSharedTeamAccess().
 * How many seats a plan includes already existed as Plan::team_seats —
 * item 10 was the first feature to actually use it.
 */
return [
    // Route names any seat is allowed to hit, isolated or shared. Everything
    // else — billing, earnings, CRM, referrals, support, disclosure
    // jurisdiction, email nurture (CRM-dependent), and the Team page
    // itself — is off-limits regardless of plan or role. Enforced by
    // App\Http\Middleware\RestrictAgencySeats; per-resource ownership
    // checks in each controller (Offer::isAccessibleBy(),
    // Generation::isAccessibleBy()) still do the finer "is this
    // specifically YOUR offer" check on top of this, and a couple of
    // these routes (calendar.update, generations.nurture-started) do a
    // further manager-only check for the actual "publish" action — see
    // ContentCalendarController and User::isTeamManager().
    'seat_allowed_routes' => [
        'dashboard',
        'offers.show',
        'offers.blog.store',
        'offers.competitor.scan',
        'offers.linkedin.keywords',
        'offers.linkedin.dm',
        'offers.linkedin.post',
        'offers.linkedin.article',
        'offers.linkedin.reply-assistant',
        'offers.linkedin.reply-assistant.store',
        'offers.youtube.script',
        'offers.youtube.metadata',
        'offers.ugc.angles',
        'offers.ugc.content',
        'offers.x.thread',
        'offers.tiktok.video',
        'offers.pinterest.pins',
        'generations.localize',
        'generations.nurture-started',
        'generations.linkedin.export',
        'generations.publish',
        'social-connections.index',
        'social-connections.redirect',
        'social-connections.callback',
        'social-connections.destroy',
        'calendar.index',
        'calendar.update',
        'swipe-files.index',
        'api-access.index',
        'api-access.tokens.store',
        'api-access.tokens.destroy',
        'profile',
        'profile.destroy',
        'notifications.poll',
        'notifications.read-all',
        'logout',
    ],

    // Route names allowed only for a seat on a "shared" (Business tier)
    // plan (User::hasSharedTeamAccess()) — an isolated-plan seat gets a
    // 403 from these regardless of the seat_allowed_routes list above,
    // since it has no reason to browse or add to the whole team's offer
    // list when it's scoped to exactly one offer.
    'shared_only_routes' => [
        'offers.index',
        'offers.create',
        'offers.store',
    ],
];
