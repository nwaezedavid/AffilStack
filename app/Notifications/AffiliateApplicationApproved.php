<?php

namespace App\Notifications;

use App\Models\AffiliateApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a newly-approved affiliate applicant — see
 * AffiliateApplicationService::approve(). Carries the one-time signed "set
 * your password" link; the account already exists (User::is_affiliate_only
 * = true) but has an unusable random password until this link is used, so
 * this notification IS the account's activation, not just an FYI.
 */
class AffiliateApplicationApproved extends Notification
{
    use Queueable;

    public function __construct(
        public AffiliateApplication $application,
        public string $setPasswordUrl,
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
            ->subject("You're approved as an affiliate!")
            ->line("Good news, {$this->application->name} — your affiliate application has been approved.")
            ->line('Set a password to access your affiliate dashboard, grab your referral link, and track your commissions.')
            ->action('Set your password', $this->setPasswordUrl)
            ->line('This link expires in 7 days.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_application_approved',
            'application_id' => $this->application->id,
        ];
    }
}
