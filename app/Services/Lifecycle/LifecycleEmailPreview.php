<?php

namespace App\Services\Lifecycle;

use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Models\User;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * The admin "Lifecycle Emails" editor's live preview and "Send test to
 * me": builds an unsaved LifecycleEmail from the form's current
 * (possibly unsaved) state, so the admin sees exactly what users will get
 * before saving — rendered through the real LifecycleMail, never a
 * look-alike.
 */
class LifecycleEmailPreview
{
    /**
     * @var array<int, string>
     */
    protected const PREVIEWABLE = ['type', 'name', 'subject', 'preheader', 'heading', 'body', 'cta_label', 'cta_url', 'theme_color', 'bonus_credits'];

    /**
     * @param  array<string, mixed>  $state
     */
    public static function fromState(array $state, ?LifecycleEmail $record = null): LifecycleEmail
    {
        $email = $record ? $record->replicate() : new LifecycleEmail;

        foreach (self::PREVIEWABLE as $field) {
            if (array_key_exists($field, $state)) {
                $email->{$field} = $field === 'bonus_credits' ? (int) $state[$field] : (string) ($state[$field] ?? '');
            }
        }

        $hero = $state['hero_image_path'] ?? null;

        if (is_array($hero)) {
            $hero = collect($hero)->first(fn ($value) => is_string($value));
        }

        $email->hero_image_path = is_string($hero) && $hero !== '' ? $hero : null;

        foreach (['subject', 'heading', 'body'] as $required) {
            if (blank($email->{$required})) {
                $email->{$required} = $required === 'body' ? '_Write the message to see it here._' : '…';
            }
        }

        return $email;
    }

    /**
     * Renders the email for $recipient. A test send to the admin uses
     * their own account so every placeholder resolves to real values.
     */
    public static function html(LifecycleEmail $email, User $recipient): string
    {
        try {
            return (new LifecycleMail($email, $recipient, ['bonus_credits' => (int) $email->bonus_credits]))->render();
        } catch (Throwable $e) {
            report($e);

            return '<p style="font-family:sans-serif;padding:24px;color:#b91c1c;">The preview could not be rendered: '.e($e->getMessage()).'</p>';
        }
    }

    public static function iframe(LifecycleEmail $email, User $recipient): HtmlString
    {
        $subject = e(app(LifecycleEmailRenderer::class)->render($email, $recipient, ['bonus_credits' => (int) $email->bonus_credits])['subject']);
        $srcdoc = e(self::html($email, $recipient));

        return new HtmlString(<<<HTML
            <div style="border:1px solid rgba(11,30,61,0.12);border-radius:14px;overflow:hidden;background:#F1F4F9;">
                <div style="padding:10px 14px;background:#fff;border-bottom:1px solid rgba(11,30,61,0.08);font-size:12px;color:#475569;">
                    <strong style="color:#0B1E3D;">Subject:</strong> {$subject}
                </div>
                <iframe title="Email preview" srcdoc="{$srcdoc}" style="display:block;width:100%;height:860px;border:0;background:#F1F4F9;" sandbox=""></iframe>
            </div>
            HTML);
    }
}
