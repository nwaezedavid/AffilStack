<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Monday weekly briefing — see App\Services\Agents\Customer\WeeklyBriefing.
 */
class WeeklyBriefingNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{wins: array<int, string>, agents: array<int, string>, next_steps: array<int, array{text: string, url: string}>, needs_approval: int}  $briefing
     */
    public function __construct(public array $briefing) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your AffilStack week: what got done and what\'s next')
            ->greeting('Hi '.MailText::escape(strtok((string) $notifiable->name, ' ') ?: 'there').',');

        $mail->line('**This week AffilStack:**');

        foreach ($this->briefing['wins'] ?: ['Kept watch over your products, links and inbox.'] as $win) {
            $mail->line('• '.MailText::escape($win));
        }

        if ($this->briefing['agents'] !== []) {
            $mail->line('**Your agents:**');

            foreach ($this->briefing['agents'] as $line) {
                $mail->line('• '.MailText::escape($line));
            }
        }

        $mail->line('**Your three best next steps:**');

        foreach ($this->briefing['next_steps'] as $i => $step) {
            $mail->line(($i + 1).'. ['.MailText::escape($step['text']).']('.$step['url'].')');
        }

        return $mail
            ->action('Open your dashboard', route('dashboard'))
            ->line('You can turn this email off on the AI Agents page.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'agent_update',
            'agent' => 'weekly_briefing',
            'title' => 'Your weekly briefing is in your inbox',
            'success' => true,
            'url' => route('agents.index'),
        ];
    }
}
