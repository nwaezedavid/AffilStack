<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Flutterwave-only (task #85): Flutterwave's Standard Checkout is a
 * one-time charge with no recurring billing, so nothing re-charges the
 * customer automatically before current_period_end. This is the warning
 * that goes out a few days ahead of that date — see
 * SubscriptionRenewalService::sendRenewalReminders(). Stripe subscribers
 * never get this; Stripe renews itself.
 */
class SubscriptionRenewalReminder extends Notification
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
        $planName = $this->subscription->plan?->name ?? 'your plan';
        $date = $this->subscription->current_period_end?->format('M j, Y') ?? 'soon';

        return (new MailMessage)
            ->subject("Your AffilStack {$planName} plan renews on {$date}")
            ->line("Your {$planName} plan is set to end on {$date}.")
            ->line('Flutterwave subscriptions on AffilStack are not charged automatically — renew manually before that date to keep your access, credits, and data uninterrupted.')
            ->action('Renew my plan', route('billing.index'))
            ->line('If you don\'t renew in time, your subscription will expire and paid features will pause until you resubscribe.');
    }
}
