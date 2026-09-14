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
 * CRM contact it was written for, through the platform's own configured
 * mailer (config('mail.default') — the same one every other transactional
 * email in this app already uses; no per-user provider setup exists).
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
    public function __construct(protected LinkCloakingService $linkCloaking) {}

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
            Mail::to($contact->email)->send(new CrmNurtureEmail($send));
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
