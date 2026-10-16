<?php

namespace App\Notifications;

use App\Models\AddonInstall;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A paid add-on's monthly renewal could not be charged.
 */
class AddonSubscriptionPaymentFailed extends Notification
{
    use Queueable;

    public function __construct(public AddonInstall $install, public string $reason = '') {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->install->addon?->liveVersion?->name ?? 'your add-on';
        $days = (int) config('marketplace.grace_days');

        return (new MailMessage)
            ->subject('Payment needed to keep your add-on')
            ->line('We couldn\'t charge your saved card for this month of "'.MailText::escape($name).'".')
            ->line("You can keep using it for {$days} more days. Pay now to keep it running without a break.")
            ->action('Pay now', route('addons.show', $this->install));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'addon_payment_failed',
            'success' => false,
            'reason' => 'Payment needed to keep "'.($this->install->addon?->liveVersion?->name ?? 'your add-on').'"',
            'url' => route('addons.show', $this->install),
        ];
    }
}
