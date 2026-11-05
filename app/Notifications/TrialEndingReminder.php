<?php

namespace App\Notifications;

use App\Models\CustomerWallet;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent 2 days before an invite-only free trial ends — see
 * TrialInviteService::sendReminders().
 */
class TrialEndingReminder extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $date = $this->subscription->trial_ends_at?->format('M j, Y') ?? 'soon';
        $price = CustomerWallet::format($this->subscription->renewalPriceCents(), (string) ($plan?->currency ?: 'USD'));

        return (new MailMessage)
            ->subject("Your AffilStack free trial ends on {$date}")
            ->line("Your free trial of the {$plan?->name} plan ends on {$date}.")
            ->line("After that we'll charge {$price}, using your AffilStack wallet balance first and then your saved card.")
            ->line('Don\'t want to continue? Cancel the trial from your Billing page before it ends and you won\'t be charged.')
            ->action('Manage my trial', route('billing.index'));
    }
}
