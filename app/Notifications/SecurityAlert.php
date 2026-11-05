<?php

namespace App\Notifications;

use App\Models\SecurityFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the admins by Tom, the Security Agent, the moment his daily scan
 * finds something new that is critical or high — an unpatched PHP such as
 * one open to PHP2Shell, or a possible web shell — so it doesn't wait for
 * someone to open the Security Center.
 */
class SecurityAlert extends Notification
{
    use Queueable;

    /**
     * @param  array<int, SecurityFinding>  $findings
     */
    public function __construct(public array $findings) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->headline())
            ->line('Tom, the Security Agent, found '.(count($this->findings) === 1 ? 'something that needs' : 'things that need').' your attention:');

        foreach ($this->findings as $finding) {
            $mail->line('• ['.strtoupper($finding->severity).'] '.$finding->title);

            if (is_string($finding->evidence['what_to_do'] ?? null)) {
                $mail->line($finding->evidence['what_to_do']);
            }
        }

        return $mail->action('Open the Security Center', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'security_alert',
            'title' => $this->headline(),
            'findings' => array_map(fn (SecurityFinding $finding) => ['id' => $finding->id, 'severity' => $finding->severity, 'title' => $finding->title], $this->findings),
            'url' => $this->url(),
        ];
    }

    protected function headline(): string
    {
        return count($this->findings) === 1
            ? 'Security alert: '.$this->findings[0]->title
            : 'Security alert: '.count($this->findings).' new issues need attention';
    }

    protected function url(): string
    {
        return route('filament.admin.resources.security-findings.index');
    }
}
