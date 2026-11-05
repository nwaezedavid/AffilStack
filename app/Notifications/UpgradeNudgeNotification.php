<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Upgrade nudge email — see App\Services\Agents\Revenue\UpgradeNudge.
 */
class UpgradeNudgeNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{key: string, label: string, used: int, limit: int, percent: int}  $limit
     * @param  array<int, string>  $highlights
     */
    public function __construct(public array $limit, public array $highlights) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("You've used {$this->limit['percent']}% of your {$this->limit['label']}")
            ->greeting('Hi '.MailText::escape(strtok((string) $notifiable->name, ' ') ?: 'there').',')
            ->line("You've used ".number_format($this->limit['used']).' of your '.number_format($this->limit['limit'])." {$this->limit['label']} this month — AffilStack is working hard for you.");

        if ($this->highlights !== []) {
            $mail->line('**So far it has:**');

            foreach ($this->highlights as $highlight) {
                $mail->line('• '.MailText::escape($highlight));
            }
        }

        return $mail
            ->line('Move up a plan to keep going without pausing.')
            ->action('See plans', route('billing.index'));
    }
}
