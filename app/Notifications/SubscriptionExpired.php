<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired from SubscriptionRenewalService::expireLapsed() (task #85) the
 * moment a subscription's current_period_end passes without a renewal —
 * the normal path for a Flutterwave customer who didn't act on the
 * reminder, or the rare Stripe case where a renewal webhook never arrived.
 */
class SubscriptionExpired extends Notification
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
            ->subject('Your AffilStack subscription has expired')
            ->line("Your {$planName} plan expired because it wasn't renewed in time.")
            ->line('Paid features are paused until you resubscribe. Your data and history are kept as-is.')
            ->action('Resubscribe', route('billing.index'));
    }
}
