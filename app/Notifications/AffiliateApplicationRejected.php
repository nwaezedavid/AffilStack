<?php

namespace App\Notifications;

use App\Models\AffiliateApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a rejected affiliate applicant — mirrors ReferralPayoutRejected's
 * style. Routed ad-hoc via Notification::route('mail', ...) rather than to
 * a User, since a rejected application never gets an account created for
 * it. See AffiliateApplicationService::reject().
 */
class AffiliateApplicationRejected extends Notification
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
            ->subject('Your affiliate application')
            ->line("Thanks for applying to become an affiliate, {$this->application->name}.")
            ->line("We're not able to approve your application at this time: {$this->application->rejection_reason}")
            ->line('If you\'d like to become an affiliate another way, signing up as a customer makes you an affiliate automatically.');
    }
}
