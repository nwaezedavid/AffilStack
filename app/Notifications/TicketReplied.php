<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by Sam (the Support Agent) to the ticket's owner whenever staff post
 * a reply — see SamAgentService::notifyTicketReplied(), triggered from
 * SupportTicketMessage's model events. Always mails: someone is waiting on
 * this reply, so it isn't gated behind notify_email_on_completion.
 */
class TicketReplied extends Notification
{
    use Queueable;

    public function __construct(public SupportTicket $ticket, public string $preview) {}

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
            ->subject("New reply on your ticket: {$this->ticket->subject}")
            ->line("Our support team replied to \"{$this->ticket->subject}\":")
            ->line('"'.$this->preview.'"')
            ->action('View the conversation', route('support.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_replied',
            'subject' => $this->ticket->subject,
            'url' => route('support.show', $this->ticket),
        ];
    }
}
