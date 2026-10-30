<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Something an AI agent did or wants approved. Always in the bell menu;
 * emailed only to customers who opted into completion emails.
 */
class AgentUpdate extends Notification
{
    use Queueable;

    public function __construct(
        public string $agent,
        public string $title,
        public string $body,
        public string $url,
        public bool $forceMail = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->forceMail || $notifiable->notify_email_on_completion ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line(MailText::escape($this->body))
            ->action('Open AffilStack', $this->url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'agent_update',
            'agent' => $this->agent,
            'title' => $this->title,
            'body' => $this->body,
            'success' => true,
            'url' => $this->url,
        ];
    }
}
