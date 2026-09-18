<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every full admin by Vault, the funding-monitor agent (see
 * App\Services\Integrations\FundingHealthChecker), whenever a third-party
 * integration or the payout wallet needs the admin to go fund/top up an
 * account before it interrupts a user. One shared class for all three
 * alert kinds (HeyGen low balance, wallet low balance, a manual funding
 * reminder falling due) since they're the same event with different copy
 * — see FundingHealthChecker for exactly when each is sent and how it
 * avoids repeating itself on every check.
 */
class FundingAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $actionUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Funding alert: {$this->title}")
            ->line($this->body)
            ->action('Open Funding Watch', $this->actionUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'funding_alert',
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->actionUrl,
        ];
    }
}
