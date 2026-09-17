<?php

use App\Console\ScheduleMonitoring;
use App\Models\ScheduledTaskRun;
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
// ->withoutOverlapping() guards every entry so a slow run never causes a
// second overlapping run to start.

ScheduleMonitoring::track(
    Schedule::command('signups:prune-expired')->daily()->withoutOverlapping(),
    'signups:prune-expired',
);

// Subscription renewal reliability (task #85). Stripe renews itself via
// webhook (see StripeWebhookController/SubscriptionRenewalService) — these
// two exist for Flutterwave, which never auto-renews, and as the
// safety-net that actually revokes access once a period lapses either way.
ScheduleMonitoring::track(
    Schedule::command('subscriptions:send-renewal-reminders')->daily()->withoutOverlapping(),
    'subscriptions:send-renewal-reminders',
);

ScheduleMonitoring::track(
    Schedule::command('subscriptions:expire-lapsed')->hourly()->withoutOverlapping(),
    'subscriptions:expire-lapsed',
);

// Tom, the Security Agent: scans daily, then (separately) carries out
// whatever a super-admin has approved and scheduled — see
// SecurityScanService / SecurityFixExecutor / SecurityFindingResource.
// Every future agent's approved work also runs through the same
// execute-due-tasks command, on the same 5-minute cadence.
ScheduleMonitoring::track(
    Schedule::command('agents:security-scan')->daily()->withoutOverlapping(),
    'agents:security-scan',
);

ScheduleMonitoring::track(
    Schedule::command('agents:execute-due-tasks')->everyFiveMinutes()->withoutOverlapping(),
    'agents:execute-due-tasks',
);

// Sam, the Support Agent: onboarding and ticket-progress notifications fire
// synchronously from model events (see SupportTicket/SupportTicketMessage
// and PaymentProcessor) — this is the one part of Sam that runs on its own
// schedule, drafting new canned-reply suggestions from how staff have
// actually been replying. Weekly is enough signal to accumulate a genuine
// pattern without spamming the Canned Replies resource with suggestions.
ScheduleMonitoring::track(
    Schedule::command('agents:support-suggest-templates')->weekly()->withoutOverlapping(),
    'agents:support-suggest-templates',
);

// Brain, the Marketing Agent: drafting and the one-click launch both happen
// on demand from the admin dashboard — this is the only part of Brain that
// runs on its own schedule, reviewing every live campaign daily through the
// connected Meta Ads MCP server and adjusting it without asking again.
ScheduleMonitoring::track(
    Schedule::command('agents:marketing-optimize-campaigns')->daily()->withoutOverlapping(),
    'agents:marketing-optimize-campaigns',
);

// Audit gap #4: self-service account deletion's 30-day grace period — see
// ProfileController::destroy() / PurgeDeletedAccounts.
ScheduleMonitoring::track(
    Schedule::command('users:purge-deleted')->daily()->withoutOverlapping(),
    'users:purge-deleted',
);

// Self-maintenance: keep the run-history table itself from growing forever.
Schedule::call(fn () => ScheduledTaskRun::where('created_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('prune-scheduled-task-runs')
    ->withoutOverlapping();
