<?php

namespace App\Notifications;

use App\Models\CrmReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A CRM contact replied to a campaign/nurture email — shown in the bell,
 * and emailed too when the user opted into email notifications.
 */
class CrmReplyReceived extends Notification
{
    use Queueable;

    public function __construct(public CrmReply $reply) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->notify_email_on_completion ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->who();

        return (new MailMessage)
            ->subject("{$who} replied to your email")
            ->line("{$who} replied:")
            ->line('"'.Str::limit((string) ($this->reply->body_clean ?: $this->reply->body_text), 300).'"')
            ->line('Open the Inbox to draft a follow-up with AI and close the sale.')
            ->action('Open the conversation', route('crm.inbox.show', $this->reply->crm_contact_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'crm_reply',
            'reason' => $this->who().' replied to your email',
            'url' => route('crm.inbox.show', $this->reply->crm_contact_id),
        ];
    }

    protected function who(): string
    {
        $contact = $this->reply->contact;

        return (string) ($contact?->name ?: ($contact?->company ?: $this->reply->from_email));
    }
}
