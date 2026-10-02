<?php

namespace App\Services\Crm;

use App\Models\CrmCampaignRecipient;
use App\Models\CrmEmailSend;
use App\Services\Credits\CreditManager;
use App\Services\Links\LinkCloakingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one step of a campaign (0 = the first email, 1+ = follow-ups) to
 * one recipient: fills the merge fields (plus an optional AI opening
 * line), cloaks and click-tracks the affiliate link exactly like
 * CrmEmailService, stamps a unique Message-ID (kept for reply matching)
 * and threads follow-ups under the first email (In-Reply-To/References).
 * Eligibility, caps and pacing are CampaignManager's job — this only sends.
 */
class CampaignSender
{
    public const LINK_MODULE = 'email_campaign';

    public function __construct(
        protected CampaignMergeFields $mergeFields,
        protected CampaignEmailRenderer $renderer,
        protected LinkCloakingService $linkCloaking,
        protected PersonalEmailSender $personalEmailSender,
        protected CampaignAiWriter $writer,
        protected CreditManager $credits,
    ) {}

    public function send(CrmCampaignRecipient $recipient, int $step): CrmEmailSend
    {
        $campaign = $recipient->campaign;
        $contact = $recipient->contact;
        $user = $campaign->user;
        $offer = $campaign->offer;
        $connection = $user->emailConnection;

        $first = $step > 0
            ? $recipient->sends()->where('kind', 'campaign')->whereNotNull('message_id')->oldest('id')->first()
            : null;

        if ($step === 0) {
            $subjectTemplate = $campaign->subject;
            $bodyTemplate = (string) $campaign->body;
        } else {
            $followUp = $campaign->followUps()[$step - 1];
            $bodyTemplate = $followUp['body'];
            $subjectTemplate = $followUp['subject'] !== '' ? $followUp['subject'] : null;
        }

        $openingLine = $step === 0 ? $this->openingLineFor($recipient) : null;
        $subject = $subjectTemplate !== null
            ? $this->mergeFields->render($subjectTemplate, $contact, $offer)
            : 'Re: '.($first?->subject ?? $this->mergeFields->render($campaign->subject, $contact, $offer));
        $rawBody = $this->mergeFields->render($bodyTemplate, $contact, $offer, $openingLine);

        $shortLink = $offer && $offer->hasLinkPlaceholder($rawBody) ? $this->linkCloaking->getOrCreateForOffer($offer, self::LINK_MODULE) : null;
        $cloaked = $offer ? $offer->cloak($rawBody, self::LINK_MODULE) : $rawBody;
        $trackingToken = $this->uniqueTrackingToken();
        $body = $shortLink ? str_replace($shortLink->short_url, route('crm.track.click', $trackingToken), $cloaked) : $cloaked;

        $messageId = self::newMessageId($connection?->connected_email ?? config('mail.from.address'));

        $send = CrmEmailSend::create([
            'user_id' => $user->id,
            'crm_contact_id' => $contact->id,
            'kind' => $step === 0 ? 'campaign' : 'follow_up',
            'crm_campaign_id' => $campaign->id,
            'crm_campaign_recipient_id' => $recipient->id,
            'sequence_step' => $step,
            'subject' => Str::limit($subject, 250, ''),
            'body' => $body,
            'link_destination' => $shortLink?->short_url,
            'tracking_token' => $trackingToken,
            'message_id' => $messageId,
            'in_reply_to' => $first?->message_id,
            'status' => 'queued',
        ]);

        try {
            $html = $this->renderer->render($user, $contact, $body, ['tracking_token' => $trackingToken]);
            $options = [
                'message_id' => $messageId,
                'in_reply_to' => $first?->message_id,
                'references' => $first?->message_id ? [$first->message_id] : [],
                'list_unsubscribe' => route('crm.unsubscribe', $contact->unsubscribeToken()),
                'thread_id' => $first?->provider_thread_id,
                'text_body' => $body,
            ];

            if ($connection) {
                $ids = $this->personalEmailSender->send($connection, (string) $contact->email, $send->subject, $html, $options);
            } else {
                Mail::html($html, fn ($message) => $message->to($contact->email)->subject($send->subject)->replyTo($user->email, $user->name));
                $ids = null;
            }

            $send->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider_message_id' => $ids['provider_message_id'] ?? null,
                'provider_thread_id' => $ids['provider_thread_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            $send->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 1000)]);
            report($e);
        }

        return $send->fresh();
    }

    /**
     * The AI personal opener, generated once per recipient and only when
     * the campaign asks for it and the account can pay — otherwise the email
     * simply goes without it.
     */
    protected function openingLineFor(CrmCampaignRecipient $recipient): ?string
    {
        $campaign = $recipient->campaign;

        if (! $campaign->personalize_opening) {
            return null;
        }

        if ($recipient->opening_line) {
            return $recipient->opening_line;
        }

        $cost = (int) config('crm.credits.personal_opening');

        if (! $this->credits->hasEnough($campaign->user, $cost)) {
            return null;
        }

        try {
            $line = $this->writer->openingLine($campaign, $recipient->contact);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($line === '') {
            return null;
        }

        try {
            $this->credits->spend($campaign->user, $cost, 'crm_personal_opening', $recipient);
        } catch (Throwable) {
            return null;
        }

        $recipient->update(['opening_line' => $line]);

        return $line;
    }

    /**
     * A globally unique RFC 5322 Message-ID on the sender's own domain.
     */
    public static function newMessageId(?string $fromEmail): string
    {
        $domain = Str::after((string) $fromEmail, '@') ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'affilstack.local');

        return '<'.Str::lower((string) Str::ulid()).'.'.Str::random(8).'@'.$domain.'>';
    }

    protected function uniqueTrackingToken(): string
    {
        do {
            $token = Str::random(32);
        } while (CrmEmailSend::where('tracking_token', $token)->exists());

        return $token;
    }
}
