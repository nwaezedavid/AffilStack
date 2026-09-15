<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every user the moment a super-admin approves and schedules one of
 * Tom's (the Security Agent's) fixes — see SecurityFindingResource. This is
 * a site-wide notice, not a personal-generation completion preference, so
 * unlike GenerationCompleted it always mails regardless of
 * notify_email_on_completion.
 */
class ScheduledMaintenanceNotice extends Notification
{
    use Queueable;

    public function __construct(
        public string $reason,
        public \DateTimeInterface $startsAt,
        public \DateTimeInterface $endsAt,
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
            ->subject('Scheduled maintenance: '.$this->startsAt->format('M j, g:ia'))
            ->line("We're carrying out a security update between {$this->startsAt->format('M j, g:ia')} and {$this->endsAt->format('g:ia T')}.")
            ->line($this->reason)
            ->line('Your data is safe — this window is just for us to apply the fix. You may notice brief disruption during that time.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'maintenance_scheduled',
            'reason' => $this->reason,
            'starts_at' => $this->startsAt->format(\DateTimeInterface::ATOM),
            'ends_at' => $this->endsAt->format(\DateTimeInterface::ATOM),
            'url' => '#',
        ];
    }
}
