<?php

namespace App\Mail;

use App\Models\CrmEmailSend;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * CRM/email dashboard: the actual email sent for one CrmEmailSend row —
 * see CrmEmailService, the only place that builds and sends these. The
 * body has already been cloaked (real affiliate link swapped in, disclosure
 * added if needed) and had that link rewritten to route through this
 * send's own click-tracking redirect before it ever reaches here.
 *
 * Sent "from" the platform's own address but reply-to the AffilStack user
 * who owns the contact, so a reply reaches them directly rather than
 * AffilStack support — this app never sends CRM outreach from a user's own
 * domain (no per-user domain/DKIM setup exists), so reply-to is how a real
 * conversation still ends up in the right inbox.
 */
class CrmNurtureEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CrmEmailSend $send) {}

    public function build(): self
    {
        return $this
            ->subject($this->send->subject)
            ->replyTo($this->send->user->email, $this->send->user->name)
            ->view('emails.crm.nurture', [
                'body' => $this->send->body,
                'senderName' => $this->send->user->name,
                'unsubscribeUrl' => route('crm.unsubscribe', $this->send->contact->unsubscribeToken()),
                'openPixelUrl' => route('crm.track.open', $this->send->tracking_token),
            ]);
    }
}
