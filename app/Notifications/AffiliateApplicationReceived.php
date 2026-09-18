<?php

namespace App\Notifications;

use App\Models\AffiliateApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the site's support address (see SiteSetting 'support_email') the
 * moment a visitor submits the public affiliate application — mirrors
 * ContactMessageReceived exactly, including being routed ad-hoc rather than
 * to a User, since no account exists for the applicant yet.
 */
class AffiliateApplicationReceived extends Notification
{
    use Queueable;

    public function __construct(public AffiliateApplication $application) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New affiliate application: '.$this->application->name)
            ->line("From: {$this->application->name} ({$this->application->email})")
            ->line('How they plan to promote: '.$this->application->promotion_channels)
            ->when($this->application->message, fn (MailMessage $mail) => $mail->line($this->application->message))
            ->action('Review application', route('filament.admin.resources.affiliate-applications.index'));
    }
}
