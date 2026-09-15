<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by Sam (the Support Agent) when a ticket is marked resolved or
 * closed — see SamAgentService::notifyTicketStatusChanged(), triggered from
 * SupportTicket's model events so it fires no matter where the status
 * change comes from (admin table, a future API, etc.).
 */
class TicketStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verb = $this->ticket->status === 'closed' ? 'closed' : 'marked resolved';

        return (new MailMessage)
            ->subject("Your ticket was {$verb}: {$this->ticket->subject}")
            ->line("Your ticket \"{$this->ticket->subject}\" has been {$verb}.")
            ->line("If this didn't actually solve things, just reply on the ticket and it'll reopen automatically.")
            ->action('View the ticket', route('support.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_status_changed',
            'subject' => $this->ticket->subject,
            'status' => $this->ticket->status,
            'url' => route('support.show', $this->ticket),
        ];
    }
}
