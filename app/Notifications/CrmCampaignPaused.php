<?php

namespace App\Notifications;

use App\Models\CrmCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A campaign (often on autopilot) stopped sending because the user's email
 * connection failed its check — they need to fix it on Email Sending.
 * Always mails: emails silently not going out is exactly what they'd want
 * to know about.
 */
class CrmCampaignPaused extends Notification
{
    use Queueable;

    public function __construct(public CrmCampaign $campaign) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Campaign paused: {$this->campaign->name}")
            ->line("We paused \"{$this->campaign->name}\" because your email connection stopped working, so no emails were sent from a broken sender.")
            ->line('Fix or reconnect it on the Email Sending page, then resume the campaign — queued contacts will be emailed automatically.')
            ->action('Fix email sending', route('email-connections.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'crm_campaign_paused',
            'success' => false,
            'reason' => "Campaign \"{$this->campaign->name}\" paused — your email connection needs attention",
            'url' => route('email-connections.index'),
        ];
    }
}
