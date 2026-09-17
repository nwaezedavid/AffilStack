<?php

namespace App\Services\Crm;

use App\Mail\CrmNurtureEmail;
use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\Generation;
use App\Services\Links\LinkCloakingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * CRM/email dashboard: actually sends one step of an AI-drafted
 * email_nurture sequence (Generation::output_meta['emails']) to the real
 * CRM contact it was written for.
 *
 * Task #1: if the sequence's owner has connected their own Gmail or SMTP
 * (User::emailConnection(), verified), the send goes out exclusively
 * through that — protects AffilStack's own shared sending domain's
 * reputation, per the backlog's stated reasoning, and means replies land
 * in the user's own inbox rather than needing a reply-to trick. With no
 * connection (the default), this still falls back to the platform's own
 * configured mailer (config('mail.default')) exactly as before. Either
 * way the same CrmNurtureEmail view is rendered — same tracking pixel,
 * unsubscribe link, and cloaked body — only the transport differs.
 *
 * Two things happen to the raw drafted body before it's ever sent:
 *  - Offer::cloak() swaps in the real (disclosed) affiliate link, exactly
 *    like every other content channel.
 *  - That cloaked short link is then rewritten to route through this
 *    send's own /e/c/{token} redirect (CrmEmailTrackingController), so
 *    clicks are attributable to this specific recipient/step rather than
 *    only to the offer+channel in aggregate. The redirect's real
 *    destination is resolved and stored server-side on the CrmEmailSend
 *    row now, not taken from the request later — see the create() call
 *    below and the controller's docblock for why that matters.
 */
class CrmEmailService
{
    public function __construct(protected LinkCloakingService $linkCloaking, protected PersonalEmailSender $personalEmailSender) {}

    public function sendSequenceStep(Generation $generation, int $step): CrmEmailSend
    {
        if ($generation->module !== 'email_nurture') {
            throw new InvalidArgumentException('Only email_nurture generations can be sent — got: '.$generation->module);
        }

        if ($generation->status !== 'completed') {
            throw new InvalidArgumentException('This sequence has not finished generating yet.');
        }

        $contact = CrmContact::find($generation->input['contact_id'] ?? null);

        if (! $contact) {
            throw new RuntimeException('This sequence\'s contact no longer exists.');
        }

        if (! $contact->email) {
            throw new RuntimeException("{$contact->name} has no email address on file.");
        }

        if ($contact->isUnsubscribed()) {
            throw new RuntimeException("{$contact->name} has unsubscribed and can no longer be emailed.");
        }

        $email = collect($generation->output_meta['emails'] ?? [])->firstWhere('step', $step);

        if (! $email) {
            throw new InvalidArgumentException("Step {$step} does not exist in this sequence.");
        }

        $offer = $generation->offer;
        $rawBody = (string) ($email['body'] ?? '');
        $hasLink = $offer->hasLinkPlaceholder($rawBody);
        $shortLink = $hasLink ? $this->linkCloaking->getOrCreateForOffer($offer, $generation->module) : null;
        $cloakedBody = $offer->cloak($rawBody, $generation->module);

        $trackingToken = $this->uniqueTrackingToken();
        $body = $shortLink
            ? str_replace($shortLink->short_url, route('crm.track.click', $trackingToken), $cloakedBody)
            : $cloakedBody;

        $send = CrmEmailSend::create([
            'user_id' => $generation->user_id,
            'crm_contact_id' => $contact->id,
            'generation_id' => $generation->id,
            'sequence_step' => $step,
            'subject' => (string) ($email['subject'] ?? 'A message for you'),
            'body' => $body,
            'link_destination' => $shortLink?->short_url,
            'tracking_token' => $trackingToken,
            'status' => 'queued',
        ]);

        try {
            $connection = $generation->user->emailConnection;
            $mailable = new CrmNurtureEmail($send);

            if ($connection && $connection->isVerified()) {
                $this->personalEmailSender->send($connection, $contact->email, $send->subject, $mailable->render());
            } else {
                Mail::to($contact->email)->send($mailable);
            }

            $send->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $e) {
            $send->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            report($e);
        }

        return $send->fresh();
    }

    protected function uniqueTrackingToken(): string
    {
        do {
            $token = Str::random(32);
        } while (CrmEmailSend::where('tracking_token', $token)->exists());

        return $token;
    }
}
