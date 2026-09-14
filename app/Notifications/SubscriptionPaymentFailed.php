<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stripe-only (task #85): fired from SubscriptionRenewalService when a
 * Stripe "invoice.payment_failed" webhook arrives for a recurring renewal
 * charge. The subscription is moved to 'past_due' at the same time, which
 * User::activeSubscription() already excludes — so access is paused the
 * moment this goes out, not after a silent grace period.
 */
class SubscriptionPaymentFailed extends Notification
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

        return (new MailMessage)
            ->subject('We could not renew your AffilStack subscription')
            ->line("Your card was declined while renewing your {$planName} plan.")
            ->line('Your access is paused until this is resolved. Stripe will keep retrying automatically, or you can update your payment details now.')
            ->action('Update payment details', route('billing.index'));
    }
}
