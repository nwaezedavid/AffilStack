<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the site's support address (see SiteSetting 'support_email') the
 * moment a visitor submits the public Contact Us form. Routed ad-hoc via
 * Notification::route('mail', ...) rather than to a User, since the
 * recipient is a settings-configured address, not necessarily an account.
 */
class ContactMessageReceived extends Notification
{
    use Queueable;

    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->contactMessage->subject ?: 'New contact form message';

        return (new MailMessage)
            ->subject("Contact form: {$subject}")
            ->line("From: {$this->contactMessage->name} ({$this->contactMessage->email})")
            ->line($this->contactMessage->message)
            ->action('View in admin', url('/admin/contact-messages/'.$this->contactMessage->id.'/edit'));
    }
}
