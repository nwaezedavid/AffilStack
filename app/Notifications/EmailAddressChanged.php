<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the OLD address when an account's sign-in email changes, so the
 * real owner hears about it even if someone else made the change.
 * Routed ad-hoc via Notification::route('mail', $oldEmail).
 */
class EmailAddressChanged extends Notification
{
    use Queueable;

    public function __construct(public string $newEmail) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Only part of the new address is shown, and it is kept out of the
        // Markdown so it can't be turned into a link.
        $masked = preg_replace('/(?<=.).(?=[^@]*@)/u', '•', $this->newEmail);

        return (new MailMessage)
            ->subject('Your '.config('app.name').' sign-in email was changed')
            ->line('The sign-in email on your '.config('app.name').' account was just changed to '.MailText::escape((string) $masked).'.')
            ->line('If you made this change, you can ignore this message.')
            ->line('If you did not, contact support straight away so we can secure your account.')
            ->action('Contact support', route('contact.show'));
    }
}
