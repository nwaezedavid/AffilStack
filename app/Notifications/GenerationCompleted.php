<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired from inside the queue job that ran a background AI generation
 * (offer research, blog article, LinkedIn content) once it reaches a
 * terminal state — success or failure. Always recorded to the database
 * (powers the bell dropdown); mail is added on top only if the user opted
 * into it on their profile, so a busy user isn't emailed for every click.
 */
class GenerationCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public string $module,
        public string $title,
        public bool $success,
        public string $url,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->notify_email_on_completion) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $moduleLabel = $this->moduleLabel();

        $mail = (new MailMessage)
            ->subject($this->success ? "Your {$moduleLabel} is ready" : "Your {$moduleLabel} failed");

        if ($this->success) {
            $mail->line("\"{$this->title}\" finished generating and is ready to view.")
                ->action('View it now', $this->url);
        } else {
            $mail->line("\"{$this->title}\" could not be generated.")
                ->line($this->errorMessage ?? 'An unknown error occurred.')
                ->action('Try again', $this->url);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => $this->module,
            'title' => $this->title,
            'success' => $this->success,
            'url' => $this->url,
            'error_message' => $this->errorMessage,
        ];
    }

    protected function moduleLabel(): string
    {
        return match ($this->module) {
            'research' => 'offer research',
            'blog_article' => 'blog article',
            'linkedin_keywords' => 'LinkedIn keyword research',
            'linkedin_dm_sequence' => 'LinkedIn DM sequence',
            'linkedin_post' => 'LinkedIn post',
            'linkedin_article' => 'LinkedIn article',
            'youtube_script' => 'YouTube video script',
            'youtube_metadata' => 'YouTube video metadata',
            'ugc_angles' => 'UGC angle ideas',
            'ugc_content' => 'UGC script & platform pack',
            default => 'generation',
        };
    }
}
