<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by Sam (the Support Agent) the moment a paid account is created —
 * see SamAgentService::onboardNewUser(), called from
 * PaymentProcessor::completeSignup(). Always mails: a first-touch welcome
 * is not a "generation finished" ping a user would want to silence via
 * notify_email_on_completion.
 */
class WelcomeAboard extends Notification
{
    use Queueable;

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
            ->subject('Welcome to AffilStack')
            ->greeting("Welcome aboard, {$notifiable->name}!")
            ->line("Your account is active — I'm Sam, and I'm here for anything you need along the way.")
            ->line('A good place to start: offer research to find your first product, then the content modules to promote it.')
            ->action('Go to your dashboard', route('dashboard'))
            ->line("If anything is unclear, ask me right from the support chat in your dashboard — I'll do my best to help, and I'll bring in a human teammate if I can't.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'url' => route('dashboard'),
        ];
    }
}
