<?php

namespace App\Services\Mobile\Push;

use App\Notifications\AddonSubscriptionEnded;
use App\Notifications\AddonSubscriptionPaymentFailed;
use App\Notifications\AgentUpdate;
use App\Notifications\CrmCampaignPaused;
use App\Notifications\CrmReplyReceived;
use App\Notifications\GenerationCompleted;
use App\Notifications\ReferralPayoutProcessed;
use App\Notifications\SocialConnectionBroken;
use App\Notifications\TicketReplied;
use App\Notifications\TicketStatusChanged;
use App\Notifications\WeeklyBriefingNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Which of AffilStack's existing in-dashboard notifications also go to the
 * customer's phone, and what they say there. Built from the notification
 * and the payload it just saved to the bell menu, so the notifications
 * themselves don't change. Anything not listed here (admin alerts,
 * marketing emails, partner-only messages) never reaches a phone.
 */
class PushMessageFactory
{
    /**
     * @param  array<string, mixed>  $data  What the notification stored for the dashboard bell.
     */
    public function make(Notification $notification, array $data): ?PushMessage
    {
        $url = is_string($data['url'] ?? null) ? $data['url'] : null;

        return match (true) {
            $notification instanceof GenerationCompleted => new PushMessage(
                PushCategories::CONTENT,
                $notification->success
                    ? 'Your '.$notification->moduleLabel().' is ready'
                    : 'Your '.$notification->moduleLabel().' couldn\'t be finished',
                $notification->success
                    ? "For {$notification->title}. Tap to see it."
                    : "For {$notification->title}. Tap to see what happened.",
                $url,
            ),
            $notification instanceof AgentUpdate => new PushMessage(PushCategories::AGENTS, $notification->title, Str::limit($notification->body, 200), $url),
            $notification instanceof WeeklyBriefingNotification => new PushMessage(PushCategories::WEEKLY, 'Your weekly briefing is ready', 'See what AffilStack did for you this week and what\'s next.', $url),
            $notification instanceof CrmReplyReceived => new PushMessage(
                PushCategories::INBOX,
                (string) ($data['reason'] ?? 'A contact replied to your email'),
                Str::limit(trim((string) ($notification->reply->body_clean ?: $notification->reply->body_text)), 160) ?: 'Tap to read it and reply.',
                $url,
            ),
            $notification instanceof TicketReplied => new PushMessage(PushCategories::SUPPORT, 'Support replied: '.$notification->ticket->subject, Str::limit($notification->preview, 160), $url),
            $notification instanceof TicketStatusChanged => new PushMessage(PushCategories::SUPPORT, 'Ticket update: '.$notification->ticket->subject, 'Your ticket is now '.str_replace('_', ' ', (string) $notification->ticket->status).'.', $url),
            $notification instanceof SocialConnectionBroken,
            $notification instanceof CrmCampaignPaused,
            $notification instanceof AddonSubscriptionPaymentFailed,
            $notification instanceof AddonSubscriptionEnded => new PushMessage(PushCategories::ACCOUNT, (string) ($data['reason'] ?? 'Your account needs attention'), 'Tap to fix it.', $url),
            $notification instanceof ReferralPayoutProcessed => new PushMessage(
                PushCategories::PAYOUTS,
                'Your payout is on its way',
                Number::currency(((int) ($data['amount_cents'] ?? 0)) / 100, strtoupper((string) ($data['currency'] ?? 'USD'))).' has been sent to you.',
                $url,
            ),
            default => null,
        };
    }
}
