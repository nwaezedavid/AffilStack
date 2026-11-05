<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Churn saver's personal email — see App\Services\Agents\Revenue\ChurnSaver.
 */
class WinBackNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $highlights
     */
    public function __construct(public string $reason, public array $highlights, public ?string $offer = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = MailText::escape(strtok((string) $notifiable->name, ' ') ?: 'there');
        $failed = $this->reason === 'payment_failed';

        $mail = (new MailMessage)
            ->subject($failed ? 'Keep everything AffilStack has built for you' : 'Your AffilStack account misses you')
            ->greeting("Hi {$first},")
            ->line($failed
                ? 'Your last AffilStack payment didn\'t go through, so your account is at risk of pausing.'
                : 'We noticed you haven\'t been in AffilStack for a little while, so here\'s a quick look at what it has done for you.');

        if ($this->highlights !== []) {
            $mail->line('**So far AffilStack has:**');

            foreach ($this->highlights as $highlight) {
                $mail->line('• '.MailText::escape($highlight));
            }
        }

        if ($this->offer) {
            $mail->line('**'.MailText::escape($this->offer).'**');
        }

        return $mail
            ->action($failed ? 'Update my payment' : 'Pick up where I left off', $failed ? route('billing.index') : route('dashboard'))
            ->line('Just reply to this email if anything is getting in your way — a real person reads every reply.');
    }
}
