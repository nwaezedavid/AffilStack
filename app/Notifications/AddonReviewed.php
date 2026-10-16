<?php

namespace App\Notifications;

use App\Models\AddonVersion;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a developer their add-on version was approved or sent back.
 */
class AddonReviewed extends Notification
{
    use Queueable;

    public function __construct(public AddonVersion $version) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->version->status === 'approved';
        $name = $this->version->name;
        $url = route('developer.addons.show', $this->version->addon);

        $mail = (new MailMessage)->subject($approved ? "Approved: {$name} {$this->version->version}" : "Changes needed: {$name} {$this->version->version}");

        if ($approved) {
            $mail->line('Good news — "'.MailText::escape($name).'" version '.MailText::escape($this->version->version).' has been approved and is now live in the AffilStack marketplace.');
        } else {
            $mail->line('"'.MailText::escape($name).'" version '.MailText::escape($this->version->version).' needs a few changes before it can go live.')
                ->line('Reviewer\'s note: '.MailText::escape((string) $this->version->review_note))
                ->line('Make the changes and submit it again.');
        }

        if ($approved && $this->version->review_note) {
            $mail->line('Reviewer\'s note: '.MailText::escape($this->version->review_note));
        }

        return $mail->action('Open the developer portal', $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approved = $this->version->status === 'approved';

        return [
            'type' => 'addon_reviewed',
            'success' => $approved,
            'reason' => $approved
                ? "Your add-on \"{$this->version->name}\" {$this->version->version} is live"
                : "Your add-on \"{$this->version->name}\" {$this->version->version} needs changes",
            'url' => route('developer.addons.show', $this->version->addon),
        ];
    }
}
