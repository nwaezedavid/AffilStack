<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Bell notification sent once when a connected social account stops
 * working (see App\Services\Social\SocialConnectionVerifier). The bell
 * renders 'reason' for typed notifications it has no dedicated copy for —
 * see NotificationController::messageFor().
 */
class SocialConnectionBroken extends Notification
{
    use Queueable;

    public function __construct(
        public string $provider,
        public string $label,
        public string $status,
        public string $detail,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'social_connection_broken',
            'provider' => $this->provider,
            'status' => $this->status,
            'success' => false,
            'reason' => $this->status === 'expired'
                ? "Your {$this->label} connection needs reconnecting"
                : "We couldn't verify your {$this->label} connection",
            'detail' => $this->detail,
            'url' => route('social-connections.index'),
        ];
    }
}
