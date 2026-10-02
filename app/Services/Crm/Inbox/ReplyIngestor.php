<?php

namespace App\Services\Crm\Inbox;

use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\CrmReply;
use App\Models\User;
use App\Notifications\CrmReplyReceived;
use App\Services\Crm\Imap\InboundEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Decides whether a message from the user's mailbox is a reply to one of
 * their CRM emails and, if so, files it: matched by In-Reply-To/References
 * against the Message-IDs we stamped on our sends, then by Gmail thread id,
 * and finally by the sender being a contact we emailed in the last N days
 * (config crm.replies.sender_match_days). A matched reply marks the
 * recipient and contact as replied (which stops their follow-ups) and
 * notifies the user. Idempotent per Message-ID.
 */
class ReplyIngestor
{
    public function ingest(User $user, InboundEmail $email, string $provider): ?CrmReply
    {
        if ($email->fromEmail === '' || $this->isOwnAddress($user, $email->fromEmail)) {
            return null;
        }

        [$send, $matchedBy] = $this->match($user, $email);

        if (! $send) {
            return null;
        }

        $messageId = $email->messageId ?? '<'.$provider.'-'.$email->externalId.'@affilstack.local>';

        if (CrmReply::where('user_id', $user->id)->where('message_id', $messageId)->exists()) {
            return null;
        }

        try {
            $reply = CrmReply::create([
                'user_id' => $user->id,
                'crm_contact_id' => $send->crm_contact_id,
                'crm_email_send_id' => $send->id,
                'crm_campaign_id' => $send->crm_campaign_id,
                'crm_campaign_recipient_id' => $send->crm_campaign_recipient_id,
                'provider' => $provider,
                'external_id' => Str::limit($email->externalId, 250, ''),
                'message_id' => Str::limit($messageId, 250, ''),
                'in_reply_to' => $email->inReplyTo ? Str::limit($email->inReplyTo, 250, '') : null,
                'references' => $email->references ? implode(' ', $email->references) : null,
                'from_email' => $email->fromEmail,
                'from_name' => $email->fromName,
                'subject' => $email->subject ? Str::limit($email->subject, 250, '') : null,
                'body_text' => Str::limit($email->textBody, 60000, ''),
                'body_clean' => Str::limit(QuotedTextStripper::strip($email->textBody), 20000),
                'matched_by' => $matchedBy,
                'received_at' => $email->date ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $receivedAt = $reply->received_at;
        $send->contact?->update(['replied_at' => $receivedAt]);
        $send->recipient?->update(['status' => 'replied', 'replied_at' => $receivedAt, 'next_step_due_at' => null]);

        $user->notify(new CrmReplyReceived($reply));

        return $reply;
    }

    /**
     * @return array{0: CrmEmailSend|null, 1: string}
     */
    protected function match(User $user, InboundEmail $email): array
    {
        $ids = $email->referencedIds();

        if ($ids !== []) {
            $send = CrmEmailSend::where('user_id', $user->id)->whereIn('message_id', $ids)->latest('id')->first();

            if ($send) {
                return [$send, 'message_id'];
            }
        }

        if ($email->threadId) {
            $send = CrmEmailSend::where('user_id', $user->id)->where('provider_thread_id', $email->threadId)->latest('id')->first();

            if ($send) {
                return [$send, 'thread'];
            }
        }

        $contactIds = CrmContact::where('user_id', $user->id)->whereRaw('lower(email) = ?', [strtolower($email->fromEmail)])->pluck('id');

        if ($contactIds->isEmpty()) {
            return [null, ''];
        }

        $since = ($email->date ?? now())->copy()->subDays((int) config('crm.replies.sender_match_days', 45));
        $send = CrmEmailSend::where('user_id', $user->id)
            ->whereIn('crm_contact_id', $contactIds)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $since)
            ->where('sent_at', '<=', ($email->date ?? now())->copy()->addMinutes(5))
            ->latest('sent_at')
            ->first();

        return [$send, 'sender'];
    }

    protected function isOwnAddress(User $user, string $email): bool
    {
        $own = array_filter([
            strtolower((string) $user->email),
            strtolower((string) $user->emailConnection?->connected_email),
            strtolower((string) $user->emailConnection?->credential('from_email')),
        ]);

        return in_array(strtolower($email), $own, true);
    }
}
