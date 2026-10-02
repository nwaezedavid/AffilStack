<?php

namespace App\Services\Agents;

use App\Mail\LifecycleMail;
use App\Models\AgentTask;
use App\Models\CannedReply;
use App\Models\LifecycleEmail;
use App\Models\LifecycleEmailLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\MaintenanceCompleted;
use App\Notifications\ScheduledMaintenanceNotice;
use App\Notifications\TicketReplied;
use App\Notifications\TicketStatusChanged;
use App\Notifications\WelcomeAboard;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Lifecycle\LifecycleEmailDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Sam, the Support Agent (AI agents phase, agent #2 of 4): owns every
 * outbound user communication for support and account-lifecycle events —
 * onboarding a new user, delivering ticket progress, and broadcasting
 * cross-agent notices Tom hands it (a scheduled/completed maintenance
 * window). It also runs the "develops/optimizes response templates as it
 * learns over time" half of its spec via suggestTemplates().
 *
 * Sam's day-to-day work never touches the platform's own codebase or
 * infrastructure the way Tom's and Tony's do, so none of it needs the
 * super-admin approval gate that AgentTask enforces for them — it runs
 * autonomously, same as the AI support chat (SupportChatService) it works
 * alongside.
 */
class SamAgentService
{
    /**
     * Minimum staff replies to learn from before Sam will draft a new
     * suggestion — below this, any pattern found would just be noise.
     */
    protected const MIN_SAMPLE_SIZE = 8;

    protected const MAX_SUGGESTIONS_PER_RUN = 3;

    public function __construct(protected AIProvider $ai) {}

    /**
     * Tom's hand-off: broadcast that a fix has been scheduled, per the
     * platform's AI-agent plan ("Tom ... passes the info to Sam to notify
     * all users of scheduled maintenance").
     */
    public function notifyScheduled(AgentTask $task): void
    {
        User::query()->chunkById(200, function ($users) use ($task) {
            Notification::send($users, new ScheduledMaintenanceNotice(
                $task->title,
                $task->scheduled_at,
                $task->scheduled_until,
            ));
        });
    }

    public function notifyCompleted(AgentTask $task): void
    {
        User::query()->chunkById(200, function ($users) use ($task) {
            Notification::send($users, new MaintenanceCompleted($task->title));
        });
    }

    /**
     * Triggered automatically on signup — see
     * PaymentProcessor::completeSignup(), the only place a paid account is
     * actually created (there is no free signup on AffilStack).
     */
    public function onboardNewUser(User $user): void
    {
        $user->notify(new WelcomeAboard);
    }

    /**
     * Triggered automatically whenever staff post a reply — see
     * SupportTicketMessage's model events.
     */
    public function notifyTicketReplied(SupportTicketMessage $message): void
    {
        $ticket = $message->ticket;

        if (! $ticket || ! $ticket->user || $ticket->user_id === $message->user_id) {
            return;
        }

        $ticket->user->notify(new TicketReplied($ticket, Str::limit($message->message, 200)));
    }

    /**
     * Triggered automatically whenever a ticket newly becomes resolved or
     * closed — see SupportTicket's model events.
     */
    public function notifyTicketStatusChanged(SupportTicket $ticket): void
    {
        $ticket->user?->notify(new TicketStatusChanged($ticket));
    }

    /**
     * Bumped the moment staff pick a template while replying — see
     * MessagesRelationManager. A slightly optimistic usage signal (a picked
     * template that's then heavily edited still counts), but good enough to
     * prioritize which templates are actually earning their place.
     */
    public function recordCannedReplyUsage(?CannedReply $reply): void
    {
        $reply?->increment('usage_count');
        $reply?->update(['last_used_at' => now()]);
    }

    /**
     * The "develops/optimizes response templates as it learns over time"
     * half of Sam's spec: looks at how staff have actually been replying on
     * recently resolved tickets, and drafts new reusable templates for
     * whatever keeps coming up that isn't covered by an existing one yet.
     * Drafts land as `status=suggested` — a staff/admin reviews and
     * activates them in the Canned Replies resource before they ever reach
     * the reply picker.
     *
     * @return int number of new suggestions created
     */
    public function suggestTemplates(): int
    {
        $samples = SupportTicketMessage::query()
            ->where('is_staff', true)
            ->whereHas('ticket', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->where('created_at', '>=', now()->subDays(30))
            ->with('ticket:id,category')
            ->latest()
            ->limit(60)
            ->get(['id', 'support_ticket_id', 'message']);

        if ($samples->count() < self::MIN_SAMPLE_SIZE) {
            return 0;
        }

        $existingTitles = CannedReply::query()->pluck('title')->map(fn ($t) => Str::lower(trim($t)));

        $transcript = $samples
            ->map(fn ($m) => '['.($m->ticket->category ?? 'other').'] '.Str::limit($m->message, 400))
            ->implode("\n---\n");

        try {
            $result = $this->ai->generateJson($this->systemPrompt(), $this->userPrompt($transcript, $existingTitles));
        } catch (AIGenerationException $e) {
            Log::warning('Sam: template suggestion generation failed.', ['error' => $e->getMessage()]);

            return 0;
        }

        $created = 0;

        foreach (array_slice($result['suggestions'] ?? [], 0, self::MAX_SUGGESTIONS_PER_RUN) as $suggestion) {
            $title = trim((string) ($suggestion['title'] ?? ''));
            $body = trim((string) ($suggestion['body'] ?? ''));

            if ($title === '' || $body === '' || $existingTitles->contains(Str::lower($title))) {
                continue;
            }

            CannedReply::create([
                'title' => $title,
                'body' => $body,
                'status' => CannedReply::STATUS_SUGGESTED,
                'source' => CannedReply::SOURCE_AI_SUGGESTED,
                'ai_rationale' => (string) ($suggestion['rationale'] ?? ''),
            ]);

            $existingTitles->push(Str::lower($title));
            $created++;
        }

        return $created;
    }

    /**
     * Lifecycle emails (birthday, festive season, re-engagement): the
     * final delivery step for one claimed send — see SendLifecycleEmail
     * and LifecycleEmailDispatcher. Re-checks that the email is still
     * wanted (the user may have opted out, been suspended, or — for a
     * re-engagement nudge — come back since it was queued), grants any
     * birthday gift credits exactly once through the credit ledger, then
     * sends. A failed send throws so the queue retries it; the log row
     * stays "queued" until it succeeds or the job finally fails.
     */
    public function deliverLifecycleEmail(LifecycleEmailLog $log): void
    {
        if ($log->status !== LifecycleEmailLog::STATUS_QUEUED) {
            return;
        }

        $user = $log->user;
        $email = $log->lifecycleEmail;
        $skipReason = match (true) {
            ! $user || ! $email => 'The user or email no longer exists.',
            $user->is_suspended => 'The account is suspended.',
            ! $user->marketingEmailsEnabled() => 'The user turned off birthday, holiday and tips emails.',
            ! $email->enabled => 'The email was switched off before it was sent.',
            $email->type === LifecycleEmail::TYPE_REENGAGEMENT
                && LifecycleEmailDispatcher::spellOccurrence($user) !== $log->occurrence => 'The user became active again.',
            default => null,
        };

        if ($skipReason !== null) {
            $log->update(['status' => LifecycleEmailLog::STATUS_SKIPPED, 'error' => $skipReason]);

            return;
        }

        $bonusCredits = $email->type === LifecycleEmail::TYPE_BIRTHDAY ? $this->grantBirthdayGift($log, $user, $email) : 0;

        Mail::to($user)->send(new LifecycleMail($email, $user, ['bonus_credits' => $bonusCredits]));

        $log->update(['status' => LifecycleEmailLog::STATUS_SENT, 'sent_at' => now(), 'error' => null]);
    }

    /**
     * Credits the birthday email's gift (0 by default) once per log row,
     * even if the send itself is retried. Team seats and affiliate-only
     * accounts have no credit balance of their own, so they get the
     * greeting without a gift.
     */
    protected function grantBirthdayGift(LifecycleEmailLog $log, User $user, LifecycleEmail $email): int
    {
        if ($email->bonus_credits <= 0 || $user->isSeat() || $user->isAffiliateOnly()) {
            return 0;
        }

        return DB::transaction(function () use ($log, $user, $email) {
            $locked = LifecycleEmailLog::whereKey($log->id)->lockForUpdate()->first();

            if ($locked->bonus_credits_granted > 0) {
                return $locked->bonus_credits_granted;
            }

            app(CreditManager::class)->grant($user, $email->bonus_credits, 'birthday_gift', $locked);
            $locked->update(['bonus_credits_granted' => $email->bonus_credits]);
            $log->bonus_credits_granted = $email->bonus_credits;

            return $email->bonus_credits;
        });
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are Sam, the support agent for AffilStack, an all-in-one SaaS for
            affiliate marketers. You are reviewing recent staff replies on resolved
            support tickets to find recurring patterns worth turning into reusable
            reply templates ("canned replies") for the support team.

            Only propose a template for something that clearly recurs across
            multiple tickets below — never invent one from a single one-off reply.
            Never propose a template whose topic is already covered by an existing
            title (case-insensitive match is enough to treat it as covered).
            Write each template body as a ready-to-send reply a support agent could
            paste with light edits — warm, concise, specific, no placeholders like
            "[name]" beyond a generic greeting.

            Respond with ONLY a JSON object: {"suggestions": [{"title": string,
            "body": string, "rationale": string}]}. Return at most 3 suggestions,
            or an empty array if nothing genuinely recurs.
            PROMPT;
    }

    /**
     * @param  Collection<int, string>  $existingTitles
     */
    protected function userPrompt(string $transcript, $existingTitles): string
    {
        $existing = $existingTitles->implode(', ') ?: 'none yet';

        return <<<PROMPT
            Existing canned reply titles (do not duplicate these topics): {$existing}

            Recent staff replies on resolved tickets, one per line, prefixed with
            the ticket's category:

            {$transcript}
            PROMPT;
    }
}
