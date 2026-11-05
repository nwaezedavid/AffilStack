<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The day-8 charge after a free trial failed — the subscription is past due
 * until the customer pays. See TrialInviteService::convertDue().
 */
class TrialPaymentFailed extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $reason = '') {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $planName = $this->subscription->plan?->name ?? 'your plan';
        $until = $this->subscription->current_period_end?->format('M j, Y') ?? 'a few days';

        return (new MailMessage)
            ->subject('We couldn\'t take your first AffilStack payment')
            ->line("Your free trial has ended, but we couldn't charge your card for the {$planName} plan.")
            ->line('Paid features are paused. Pay from your Billing page by '.$until.' to keep your account, credits and data.')
            ->action('Pay now', route('billing.index'));
    }
}
