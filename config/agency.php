<?php

/**
 * Agency / team seats (Phase 3, item 10).
 *
 * A "seat" is a normal User row with `agency_owner_id` set — it logs in on
 * its own, but every credit it spends is billed to the owner
 * (User::billableUser(), enforced centrally in CreditManager) and it's
 * scoped to exactly one offer (`seat_offer_id`), matching the backlog's
 * "draft only, no publish; one product only". How many seats a plan
 * includes already existed as Plan::team_seats — this is the first feature
 * to actually use it.
 */
return [
    // Route names a seat is allowed to hit. Everything else — billing,
    // earnings, CRM, referrals, support, the offers list, creating new
    // offers, disclosure jurisdiction, email nurture (CRM-dependent), and
    // the Team page itself — is off-limits. Enforced by
    // App\Http\Middleware\RestrictAgencySeats; per-resource ownership
    // checks in each controller (Offer::isAccessibleBy(),
    // Generation::isAccessibleBy()) still do the finer "is this
    // specifically YOUR assigned offer" check on top of this.
    'seat_allowed_routes' => [
        'dashboard',
        'offers.show',
        'offers.blog.store',
        'offers.competitor.scan',
        'offers.linkedin.keywords',
        'offers.linkedin.dm',
        'offers.linkedin.post',
        'offers.linkedin.article',
        'offers.youtube.script',
        'offers.youtube.metadata',
        'offers.ugc.angles',
        'offers.ugc.content',
        'offers.x.thread',
        'offers.tiktok.video',
        'generations.localize',
        'calendar.index',
        'calendar.update',
        'swipe-files.index',
        'profile',
        'notifications.poll',
        'notifications.read-all',
        'logout',
    ],
];
