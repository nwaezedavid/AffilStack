<?php

namespace App\Notifications;

use App\Models\PartnerAnnouncement;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * An admin announcement emailed to partners — see the "Email partners"
 * action on Partners → Partner News.
 */
class PartnerAnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PartnerAnnouncement $announcement) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", $this->announcement->body)), ENT_QUOTES | ENT_HTML5));

        $message = (new MailMessage)
            ->subject($this->announcement->title)
            ->greeting('Hi '.MailText::escape(Str::of((string) $notifiable->name)->before(' ')->value() ?: 'there').',');

        foreach (array_slice(array_values(array_filter(array_map('trim', explode("\n", $text)))), 0, 12) as $line) {
            $message->line(MailText::escape($line));
        }

        return $message
            ->action('Open your Partner Portal', route('partner.news'))
            ->line('You get these emails because you\'re an AffilStack partner.');
    }
}
