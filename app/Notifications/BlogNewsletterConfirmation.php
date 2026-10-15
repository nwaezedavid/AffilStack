<?php

namespace App\Notifications;

use App\Support\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Confirm your subscription" — the double opt-in email for the blog
 * newsletter. Nobody is added to the CRM until they click the link, so a
 * stranger can't sign someone else up. Routed ad-hoc via
 * Notification::route('mail', $email).
 */
class BlogNewsletterConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $confirmUrl) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteName = Brand::name();

        return (new MailMessage)
            ->subject('Confirm your '.$siteName.' newsletter subscription')
            ->line('Thanks for signing up to the '.$siteName.' newsletter on our blog.')
            ->line('Please confirm it\'s really you by clicking the button below. The link works for 7 days.')
            ->action('Yes, subscribe me', $this->confirmUrl)
            ->line('If you didn\'t sign up, just ignore this email and you won\'t hear from us.');
    }
}
