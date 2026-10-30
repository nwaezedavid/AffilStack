<?php

use App\Console\ScheduleMonitoring;
use App\Models\ApiRequestLog;
use App\Models\IdempotencyKey;
use App\Models\ScheduledTaskRun;
use App\Models\UserActivityDay;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every scheduled task below is wrapped in ScheduleMonitoring::track() so
// its run history shows up in Filament under System > Scheduled Task Runs
// — an admin can see it actually ran, not just assume it did. New
// scheduled work (subscription renewals, UGC video cleanup, agent tasks,
// etc.) should follow the same pattern as each of those features is built.
// ->withoutOverlapping(30) guards every entry so a slow run never causes a
// second overlapping run to start. The 30-minute lock expiry matters on
// Hostinger: if the host kills a run, its lock clears within half an hour
// instead of the default 24 hours (which would skip e.g. expiring lapsed
// subscriptions for a whole day).

ScheduleMonitoring::track(
    Schedule::command('signups:prune-expired')->daily()->withoutOverlapping(30),
    'signups:prune-expired',
);

// Subscription renewal reliability (task #85). Stripe renews itself via
// webhook (see StripeWebhookController/SubscriptionRenewalService) — these
// two exist for Flutterwave, which never auto-renews, and as the
// safety-net that actually revokes access once a period lapses either way.
ScheduleMonitoring::track(
    Schedule::command('subscriptions:send-renewal-reminders')->daily()->withoutOverlapping(30),
    'subscriptions:send-renewal-reminders',
);

ScheduleMonitoring::track(
    Schedule::command('subscriptions:expire-lapsed')->hourly()->withoutOverlapping(30),
    'subscriptions:expire-lapsed',
);

// Self-service plan downgrade (audit item #2), non-Stripe half — see
// ApplyPendingDowngrades. Stripe's own recurring billing applies its
// scheduled downgrade at the next renewal webhook instead.
ScheduleMonitoring::track(
    Schedule::command('subscriptions:apply-pending-downgrades')->daily()->withoutOverlapping(30),
    'subscriptions:apply-pending-downgrades',
);

// Tom, the Security Agent: scans daily, then (separately) carries out
// whatever a super-admin has approved and scheduled — see
// SecurityScanService / SecurityFixExecutor / SecurityFindingResource.
// Every future agent's approved work also runs through the same
// execute-due-tasks command, on the same 5-minute cadence.
ScheduleMonitoring::track(
    Schedule::command('agents:security-scan')->daily()->withoutOverlapping(30),
    'agents:security-scan',
);

ScheduleMonitoring::track(
    Schedule::command('agents:execute-due-tasks')->everyFiveMinutes()->withoutOverlapping(30),
    'agents:execute-due-tasks',
);

// Sam, the Support Agent: onboarding and ticket-progress notifications fire
// synchronously from model events (see SupportTicket/SupportTicketMessage
// and PaymentProcessor) — this is the one part of Sam that runs on its own
// schedule, drafting new canned-reply suggestions from how staff have
// actually been replying. Weekly is enough signal to accumulate a genuine
// pattern without spamming the Canned Replies resource with suggestions.
ScheduleMonitoring::track(
    Schedule::command('agents:support-suggest-templates')->weekly()->withoutOverlapping(30),
    'agents:support-suggest-templates',
);

// Brain, the Marketing Agent: drafting and the one-click launch both happen
// on demand from the admin dashboard — this is the only part of Brain that
// runs on its own schedule, reviewing every live campaign daily through the
// connected Meta Ads MCP server and adjusting it without asking again.
ScheduleMonitoring::track(
    Schedule::command('agents:marketing-optimize-campaigns')->daily()->withoutOverlapping(30),
    'agents:marketing-optimize-campaigns',
);

// Audit gap #4: self-service account deletion's 30-day grace period — see
// ProfileController::destroy() / PurgeDeletedAccounts.
ScheduleMonitoring::track(
    Schedule::command('users:purge-deleted')->daily()->withoutOverlapping(30),
    'users:purge-deleted',
);

// Vault, the funding-monitor agent: daily live-checks HeyGen and the payout
// wallet, and evaluates whether either manual funding reminder (Anthropic,
// Meta ad spend) has fallen due — see FundingHealthChecker.
ScheduleMonitoring::track(
    Schedule::command('integrations:check-funding-health')->daily()->withoutOverlapping(30),
    'integrations:check-funding-health',
);

// The active half of the 404 monitor — see SiteLinkHealthChecker. Every
// hour rather than daily since it's cheap (in-process requests, no real
// network calls) and a dead link is worth catching quickly.
ScheduleMonitoring::track(
    Schedule::command('seo:check-links')->hourly()->withoutOverlapping(30),
    'seo:check-links',
);

// Image WebP optimization: every future upload already converts itself at
// save time (see WebpFileUpload), so this daily run is just a safety net —
// cheap (WebpImageConverter treats an already-.webp path as a no-op) and
// catches anything that ever slipped through (a future upload field that
// forgot to use WebpFileUpload, a path set directly via a seeder/import).
ScheduleMonitoring::track(
    Schedule::command('images:optimize-to-webp')->daily()->withoutOverlapping(30),
    'images:optimize-to-webp',
);

// Built-in GitHub sync: pushes this application's own working copy to the
// super-admin's connected repo (Filament: System > GitHub Sync) whenever
// that connection's auto-sync toggle is on — see GitHubSyncService for the
// commit/push mechanics and its .env-leak defense-in-depth guard. The same
// service also powers the page's on-demand "Sync now" button.
ScheduleMonitoring::track(
    Schedule::command('github:sync')->daily()->withoutOverlapping(30),
    'github:sync',
);

// Self-maintenance: keep the run-history table itself from growing forever.
Schedule::call(fn () => ScheduledTaskRun::where('created_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('prune-scheduled-task-runs')
    ->withoutOverlapping(30);

// Worker died mid-job (deploy restart, reboot, hard timeout) — without this
// the generation's spinner would run forever. See FailStuckGenerations.
ScheduleMonitoring::track(
    Schedule::command('generations:fail-stuck')->everyFifteenMinutes()->withoutOverlapping(30),
    'generations:fail-stuck',
);

// High-volume log tables that otherwise grow without bound. Idempotency keys
// only need to outlive a client's retry window; request/delivery logs back
// the 30-day dashboard stats and short-term debugging.
Schedule::call(function () {
    IdempotencyKey::where('created_at', '<', now()->subDays(7))->delete();
    ApiRequestLog::where('created_at', '<', now()->subDays(90))->delete();
    WebhookDelivery::where('created_at', '<', now()->subDays(90))->delete();
})
    ->daily()
    ->name('prune-api-logs')
    ->withoutOverlapping(30);

Schedule::command('activitylog:clean --force')->weekly()->withoutOverlapping(30);

// Yearly plans are paid once a year but sold as "credits / month" — this is
// what actually delivers each month's allowance. See GrantMonthlyCredits.
ScheduleMonitoring::track(
    Schedule::command('subscriptions:grant-monthly-credits')->daily()->withoutOverlapping(30),
    'subscriptions:grant-monthly-credits',
);

// ---------------------------------------------------------------------
// Agent 08 — CRM campaigns, reply detection and email-connection checks.
// Campaign sends are queued jobs; this is the safety net for queued
// contacts held back by a daily cap and for follow-ups that come due.
// Reply polling runs within a ~45s budget (config/crm.php) so a tick
// never outlives shared hosting's limits.
// ---------------------------------------------------------------------
ScheduleMonitoring::track(
    Schedule::command('crm:send-campaigns')->everyFiveMinutes()->withoutOverlapping(30),
    'crm:send-campaigns',
);

ScheduleMonitoring::track(
    Schedule::command('crm:poll-replies')->everyFiveMinutes()->withoutOverlapping(30),
    'crm:poll-replies',
);

ScheduleMonitoring::track(
    Schedule::command('crm:verify-email-connections')->daily()->withoutOverlapping(30),
    'crm:verify-email-connections',
);

// ── Customer AI agents ─────────────────────────────────────────────────
// Reply Copilot runs every minute (inbox check + drafts + due autopilot
// sends, within a ~50s budget); the others run nightly or weekly. Each is
// switched per customer on the AI Agents page and gated by the plan.
ScheduleMonitoring::track(
    Schedule::command('agents:reply-copilot')->everyMinute()->withoutOverlapping(5),
    'agents:reply-copilot',
);

foreach ([
    'lead_hunter' => fn ($event) => $event->dailyAt('02:10'),
    'commission_watchdog' => fn ($event) => $event->dailyAt('03:20'),
    'content_autopilot' => fn ($event) => $event->weeklyOn(1, '04:30'),
    'offer_scout' => fn ($event) => $event->weeklyOn(3, '05:40'),
    'weekly_briefing' => fn ($event) => $event->weeklyOn(1, '07:15'),
    'churn_saver' => fn ($event) => $event->dailyAt('10:05'),
    'upgrade_nudge' => fn ($event) => $event->dailyAt('11:25'),
] as $agent => $when) {
    ScheduleMonitoring::track(
        $when(Schedule::command("agents:run {$agent}"))->withoutOverlapping(60),
        "agents:run {$agent}",
    );
}

// ── Lifecycle emails & activity (agent 03) ──────────────────────────────
// Birthday, festive-season and re-engagement emails — see
// LifecycleEmailDispatcher. Hourly because each user gets theirs at the
// send hour (default 9am) in their OWN timezone; every run is idempotent
// (a unique send-log row is claimed before anything is queued), and the
// actual sends go through the database queue the cron worker drains.
ScheduleMonitoring::track(
    Schedule::command('lifecycle:send-birthday')->hourly()->withoutOverlapping(30),
    'lifecycle:send-birthday',
);

ScheduleMonitoring::track(
    Schedule::command('lifecycle:send-festive')->hourly()->withoutOverlapping(30),
    'lifecycle:send-festive',
);

ScheduleMonitoring::track(
    Schedule::command('lifecycle:send-reengagement')->hourly()->withoutOverlapping(30),
    'lifecycle:send-reengagement',
);

// Daily-active-user history behind the admin activity sparkline.
Schedule::call(fn () => UserActivityDay::where('date', '<', now()->subDays((int) config('lifecycle.activity_history_days', 90))->toDateString())->delete())
    ->daily()
    ->name('prune-user-activity-days')
    ->withoutOverlapping(30);

// --- Relaunch (agent 07): daily live re-check of every connected social
// account (YouTube/LinkedIn/TikTok/Instagram), refreshing tokens where the
// platform allows and notifying the owner once when one breaks — see
// VerifySocialConnections / SocialConnectionVerifier.
ScheduleMonitoring::track(
    Schedule::command('social-connections:verify')->dailyAt('05:17')->withoutOverlapping(30),
    'social-connections:verify',
);
// --- end agent 07 ---

// --- Agent 04: first-party analytics ----------------------------------------
// Interest & intent profiles behind Analytics → Audience Insights and the
// ad audiences: incremental every 30 minutes, a full rebuild nightly (keeps
// time-based stages and the 30-day engagement score current). The purge
// enforces the retention period set in Analytics → Cookie Consent.
ScheduleMonitoring::track(
    Schedule::command('analytics:build-profiles')->everyThirtyMinutes()->withoutOverlapping(30),
    'analytics:build-profiles',
);

ScheduleMonitoring::track(
    Schedule::command('analytics:build-profiles --full')->dailyAt('02:41')->withoutOverlapping(30),
    'analytics:build-profiles --full',
);

ScheduleMonitoring::track(
    Schedule::command('analytics:purge')->dailyAt('03:13')->withoutOverlapping(30),
    'analytics:purge',
);
// --- end agent 04 ---

// --- Feature 5: affiliate network connections --------------------------------
// Exchange rates first (earnings totals across currencies), then every
// connected network's programs and commissions. Customers can also press
// "Sync now" between runs.
ScheduleMonitoring::track(
    Schedule::command('fx:refresh')->dailyAt('03:37')->withoutOverlapping(30),
    'fx:refresh',
);

ScheduleMonitoring::track(
    Schedule::command('networks:sync')->dailyAt('04:23')->withoutOverlapping(30),
    'networks:sync',
);

// Feature 7: paid marketplace add-ons renew from saved cards; developers'
// earnings become payable once the refund window has passed.
ScheduleMonitoring::track(
    Schedule::command('marketplace:renew')->hourlyAt(17)->withoutOverlapping(30),
    'marketplace:renew',
);

ScheduleMonitoring::track(
    Schedule::command('marketplace:release-earnings')->dailyAt('05:11')->withoutOverlapping(30),
    'marketplace:release-earnings',
);
