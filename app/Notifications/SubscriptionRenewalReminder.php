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

        if ($this->subscription->assigned_by_admin) {
            return (new MailMessage)
                ->subject("Your complimentary AffilStack {$planName} month ends on {$date}")
                ->line("The free month of the {$planName} plan we gave you ends on {$date}.")
                ->line('To keep it, pay for the plan from your Billing page before then — or choose another plan.')
                ->action('Keep my plan', route('billing.index'))
                ->line('If you don\'t, your subscription will expire and paid features will pause until you subscribe.');
        }

        return (new MailMessage)
            ->subject("Your AffilStack {$planName} plan renews on {$date}")
            ->line("Your {$planName} plan is set to end on {$date}.")
            ->line('We\'ll try to renew it automatically from your AffilStack wallet balance and saved card. If neither can cover it, renew from your Billing page before that date to keep your access, credits, and data uninterrupted.')
            ->action('Renew my plan', route('billing.index'))
            ->line('If it isn\'t renewed in time, your subscription will expire and paid features will pause until you resubscribe.');
    }
}
