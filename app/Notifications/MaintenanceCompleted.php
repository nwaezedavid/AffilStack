<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Follow-up to ScheduledMaintenanceNotice once SecurityFixExecutor finishes
 * successfully — closes the loop for users who saw the earlier notice.
 */
class MaintenanceCompleted extends Notification
{
    use Queueable;

    public function __construct(public string $reason) {}

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
            ->subject('Maintenance complete')
            ->line("The scheduled security update is complete: {$this->reason}")
            ->line('Everything is back to normal and no data was affected.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'maintenance_completed',
            'reason' => $this->reason,
            'url' => '#',
        ];
    }
}
