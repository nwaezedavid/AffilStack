<?php

namespace App\Notifications;

use App\Models\AddonInstall;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A paid add-on has ended (cancelled, unpaid or no longer sold).
 */
class AddonSubscriptionEnded extends Notification
{
    use Queueable;

    public function __construct(public AddonInstall $install) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->install->addon?->liveVersion?->name ?? 'Your add-on';

        return (new MailMessage)
            ->subject('Your add-on has ended')
            ->line('"'.MailText::escape($name).'" has been removed from your AffilStack account, and any app connection to it has been switched off.')
            ->line('You can add it again from the marketplace at any time.')
            ->action('Browse add-ons', route('marketplace.browse'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'addon_ended',
            'success' => false,
            'reason' => '"'.($this->install->addon?->liveVersion?->name ?? 'An add-on').'" has ended',
            'url' => route('marketplace.browse'),
        ];
    }
}
