<?php

namespace App\Mail;

use App\Models\LifecycleEmail;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * A birthday, festive-season or re-engagement email — see
 * LifecycleEmailRenderer for the content and SendLifecycleEmail (the
 * queued job) for when it goes out. Carries RFC 8058 one-click
 * List-Unsubscribe headers so Gmail/Yahoo show their native unsubscribe
 * button and bulk-sender rules are met.
 */
class LifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context  extra placeholder values (e.g. bonus_credits)
     */
    public function __construct(public LifecycleEmail $email, public User $recipient, public array $context = []) {}

    /**
     * @return array<string, mixed>
     */
    protected function rendered(): array
    {
        return app(LifecycleEmailRenderer::class)->render($this->email, $this->recipient, $this->context);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered()['subject']);
    }

    public function content(): Content
    {
        $rendered = $this->rendered();

        return new Content(view: 'emails.lifecycle.message', with: [
            'subject' => $rendered['subject'],
            'preheader' => $rendered['preheader'],
            'heading' => $rendered['heading'],
            'bodyHtml' => $rendered['body_html'],
            'ctaLabel' => $rendered['cta_label'],
            'ctaUrl' => $rendered['cta_url'],
            'heroUrl' => $rendered['hero_url'],
            'themeColor' => $rendered['theme_color'],
            'unsubscribeUrl' => $rendered['unsubscribe_url'],
            'siteName' => $rendered['site_name'],
            'logoUrl' => $rendered['logo_url'],
            'profileUrl' => $rendered['profile_url'],
        ]);
    }

    public function headers(): Headers
    {
        if (! $this->recipient->exists) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<'.app(LifecycleEmailRenderer::class)->unsubscribeUrl($this->recipient).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
