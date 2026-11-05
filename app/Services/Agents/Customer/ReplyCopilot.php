<?php

namespace App\Services\Agents\Customer;

use App\Models\CrmReply;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\AgentUpdate;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Crm\Inbox\InboxReplier;
use App\Services\Crm\Inbox\ReplyPollingService;
use App\Support\Agents\AgentSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reply Copilot: every minute, checks the inboxes of customers who have it
 * switched on, sorts each new reply by intent and drafts the best response
 * (with the product's tracked link where it fits). The customer sends it
 * with one tap — or, on plans with autopilot and only when they've turned
 * it on, the safe cases (an interested lead or a plain question, judged
 * with high confidence) go out by themselves a few minutes later.
 *
 * Unsubscribe requests are honoured straight away. Each draft costs the
 * same credits as a manual AI draft (config('crm.credits.reply_draft')).
 */
class ReplyCopilot
{
    public const INTENTS = [
        'interested' => 'Interested',
        'question' => 'Question',
        'objection' => 'Objection',
        'not_now' => 'Not now',
        'unsubscribe' => 'Wants no more emails',
        'out_of_office' => 'Out of office',
        'other' => 'Other',
    ];

    /** Intents autopilot may answer by itself. */
    public const SAFE_INTENTS = ['interested', 'question'];

    public const AUTOPILOT_MIN_CONFIDENCE = 80;

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
        protected InboxReplier $replier,
        protected ReplyPollingService $polling,
    ) {}

    /**
     * One pass of the agent: poll, draft, send what's due.
     *
     * @return array{polled: int, drafted: int, sent: int}
     */
    public function tick(float $budgetSeconds = 50): array
    {
        $deadline = microtime(true) + $budgetSeconds;
        $polled = $drafted = $sent = 0;

        foreach ($this->activeUsers() as $user) {
            if (microtime(true) > $deadline) {
                break;
            }

            $connection = $user->emailConnection;

            if ($connection && $connection->replyDetectionMethod()) {
                try {
                    $this->polling->pollOne($connection);
                    $polled++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        CrmReply::query()
            ->whereNull('copilot_processed_at')
            ->where('created_at', '>=', now()->subDays(2))
            ->whereIn('user_id', $this->activeUsers()->pluck('id'))
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (CrmReply $reply) use (&$drafted, $deadline): void {
                if (microtime(true) <= $deadline && $this->process($reply)) {
                    $drafted++;
                }
            });

        $sent = $this->sendDue();

        return ['polled' => $polled, 'drafted' => $drafted, 'sent' => $sent];
    }

    /**
     * Sorts and drafts one reply. Returns whether a draft was written.
     */
    public function process(CrmReply $reply): bool
    {
        $user = $reply->user;
        $contact = $reply->contact;

        if (! $user || ! $contact || $reply->copilot_processed_at || ! AgentSettings::enabled($user, 'reply_copilot')) {
            return false;
        }

        // Only the newest message from a contact needs an answer.
        if ($contact->replies()->where('id', '>', $reply->id)->exists()) {
            $reply->update(['copilot_status' => 'skipped', 'copilot_processed_at' => now()]);

            return false;
        }

        $cost = (int) config('crm.credits.reply_draft');

        if (! $this->credits->hasEnough($user, $cost)) {
            $reply->update(['copilot_status' => 'no_credits', 'copilot_processed_at' => now()]);

            return false;
        }

        $offer = $this->replier->offerFor($reply);

        try {
            $result = $this->ai->generateJson($this->systemPrompt(), $this->context($reply, $offer));
        } catch (Throwable $e) {
            report($e);

            // Left unprocessed so the next minute's pass tries again.
            return false;
        }

        $intent = array_key_exists((string) ($result['intent'] ?? ''), self::INTENTS) ? (string) $result['intent'] : 'other';
        $confidence = max(0, min(100, (int) ($result['confidence'] ?? 0)));
        $summary = Str::limit(trim((string) ($result['summary'] ?? '')), 280, '…');
        $draft = trim((string) ($result['draft'] ?? ''));

        if ($intent === 'unsubscribe') {
            $contact->forceFill(['unsubscribed_at' => now()])->save();
            $reply->update(['copilot_intent' => $intent, 'copilot_summary' => $summary, 'copilot_confidence' => $confidence, 'copilot_status' => 'skipped', 'copilot_processed_at' => now()]);
            AgentSettings::log($user, 'reply_copilot', 'unsubscribed', ($contact->name ?: $contact->email).' asked for no more emails, so they were unsubscribed.', $contact);

            return false;
        }

        if ($intent === 'out_of_office' || $draft === '') {
            $reply->update(['copilot_intent' => $intent, 'copilot_summary' => $summary, 'copilot_confidence' => $confidence, 'copilot_status' => 'skipped', 'copilot_processed_at' => now()]);

            return false;
        }

        $this->credits->spend($user, $cost, 'crm_reply_draft', $reply);

        $autopilot = AgentSettings::planAllowsAutopilot($user)
            && (bool) AgentSettings::get($user, 'reply_copilot', 'autopilot', false)
            && in_array($intent, self::SAFE_INTENTS, true)
            && $confidence >= self::AUTOPILOT_MIN_CONFIDENCE
            && ! $contact->isUnsubscribed()
            && $user->emailConnection?->isVerified();

        $reply->update([
            'copilot_intent' => $intent,
            'copilot_summary' => $summary,
            'copilot_confidence' => $confidence,
            'copilot_draft' => $draft,
            'copilot_status' => $autopilot ? 'auto_queued' : 'drafted',
            // A short, human-looking pause before autopilot answers.
            'copilot_send_at' => $autopilot ? now()->addMinutes(random_int(4, 12)) : null,
            'copilot_processed_at' => now(),
        ]);

        $name = $contact->name ?: $contact->email;
        AgentSettings::log($user, 'reply_copilot', $autopilot ? 'auto_queued' : 'drafted', ($autopilot ? 'Autopilot will answer ' : 'Drafted a reply to ').$name.' ('.self::INTENTS[$intent].').', $reply);

        $user->notify(new AgentUpdate(
            'reply_copilot',
            $autopilot ? "Autopilot is answering {$name}" : "{$name} replied — your answer is ready",
            $summary !== '' ? $summary : 'Reply Copilot read the message and drafted a response.',
            route('crm.inbox.show', $contact),
        ));

        return true;
    }

    /**
     * Sends autopilot replies whose short delay has passed.
     */
    public function sendDue(): int
    {
        $sent = 0;

        CrmReply::query()
            ->where('copilot_status', 'auto_queued')
            ->where('copilot_send_at', '<=', now())
            ->with(['user', 'contact'])
            ->limit(25)
            ->get()
            ->each(function (CrmReply $reply) use (&$sent): void {
                $user = $reply->user;

                // Settings changed, a newer message arrived, or they unsubscribed: hand it back for a human.
                if (! AgentSettings::planAllowsAutopilot($user) || ! AgentSettings::get($user, 'reply_copilot', 'autopilot', false)
                    || $reply->contact->replies()->where('id', '>', $reply->id)->exists() || $reply->contact->isUnsubscribed()) {
                    $reply->update(['copilot_status' => 'drafted', 'copilot_send_at' => null]);

                    return;
                }

                try {
                    $subject = (string) ($reply->subject ?: $reply->send?->subject ?: 'our conversation');
                    $this->replier->send($user, $reply, str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject, $this->draftForEditing($reply));
                    $reply->update(['copilot_status' => 'sent', 'copilot_send_at' => null]);
                    AgentSettings::log($user, 'reply_copilot', 'auto_sent', 'Autopilot answered '.($reply->contact->name ?: $reply->contact->email).'.', $reply);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $reply->update(['copilot_status' => 'drafted', 'copilot_send_at' => null]);
                }
            });

        return $sent;
    }

    /**
     * The draft as the customer edits it: the link placeholder made readable.
     */
    public function draftForEditing(CrmReply $reply): string
    {
        return str_replace(Offer::LINK_PLACEHOLDER, '{affiliate_link}', (string) $reply->copilot_draft);
    }

    /**
     * @return Collection<int, User>
     */
    protected function activeUsers()
    {
        return User::query()
            ->whereNull('agency_owner_id')
            ->whereHas('activeSubscription.plan', fn ($query) => $query->where('agents_included', true))
            ->whereHas('emailConnection')
            ->get()
            ->filter(fn (User $user): bool => AgentSettings::enabled($user, 'reply_copilot'))
            ->values();
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are Reply Copilot, an expert, consultative sales assistant for an affiliate marketer.
            A prospect answered the marketer's email. Read the thread and return JSON with exactly:
            - "intent": one of "interested", "question", "objection", "not_now", "unsubscribe", "out_of_office", "other".
            - "confidence": 0-100, how sure you are of the intent and that the draft is safe to send unedited.
            - "summary": one sentence (max 25 words) telling the marketer what the prospect wants.
            - "draft": the best next reply. Answer their actual question or objection honestly, propose one
              easy next step, and include the link placeholder {{AFFILIATE_LINK}} exactly once when it fits
              naturally. 50-130 words, plain text, warm, no hype, no emojis. Start with "Hi <first name>," and
              end with "Best," and no name. Use "" for "unsubscribe" and "out_of_office" — never sell to
              someone who asked to stop.
            Never invent prices, discounts, guarantees or facts not in the product details.
            PROMPT;
    }

    protected function context(CrmReply $reply, ?Offer $offer): string
    {
        $transcript = $this->replier->thread($reply->contact)
            ->take(-8)
            ->map(fn (array $item): string => ($item['direction'] === 'out' ? 'ME' : 'THEM').': '.Str::limit($item['body'], 1200))
            ->implode("\n\n");

        $product = $offer
            ? "Product: {$offer->product_name}\nOfficial URL: {$offer->product_url}\nIdeal customer: ".($offer->ideal_customer_summary ?: 'n/a')."\nAngle: ".($offer->recommended_angle ?: 'n/a')
            : 'Product: not linked to this conversation — do not include the link placeholder.';

        return $product."\n\nConversation so far (oldest first):\n".$transcript."\n\nTheir latest message:\n".($reply->body_clean ?: $reply->body_text);
    }
}
