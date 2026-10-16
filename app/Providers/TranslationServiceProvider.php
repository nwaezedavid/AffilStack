<?php

namespace App\Providers;

use App\Support\Translation\Catalog;
use App\Support\Translation\HtmlTranslator;
use App\Support\Translation\Languages;
use App\Support\Translation\Mark;
use App\Support\Translation\TranslationStore;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Feature 6 wiring: every Blade echo goes through Mark (so customer data
 * can be kept out of translation), and emails to customers who chose a
 * language are translated as they're sent.
 */
class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranslationStore::class);
    }

    public function boot(): void
    {
        Blade::setEchoFormat('\\'.Mark::class.'::e(%s)');

        Blade::precompiler(fn (string $value) => (string) preg_replace_callback(
            '/(@)?\{!!\s*(.+?)\s*!!\}/s',
            fn (array $match) => $match[1] !== '' ? $match[0] : '{!! \\'.Mark::class.'::raw('.$match[2].') !!}',
            $value,
        ));

        // Emails (notifications) to someone who chose another language:
        // mark customer data while the email is rendered, translate it on
        // the way out.
        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            if ($event->channel === 'mail' && $this->emailLocale($event->notifiable) !== null) {
                Mark::$protect = HtmlTranslator::available();

                // Notification emails are built from AffilStack's own
                // Markdown; values in it are marked as they're written.
                Mark::$trustHtml = Mark::$protect;
            }
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel === 'mail') {
                Mark::$protect = false;
                Mark::$trustHtml = false;
            }
        });

        // Never carried over into the next queued job if a send failed.
        Event::listen([JobProcessing::class, JobProcessed::class, NotificationFailed::class], function () {
            Mark::$protect = false;
            Mark::$trustHtml = false;
        });

        Event::listen(MessageSending::class, function (MessageSending $event) {
            $this->translateEmail($event->message);
        });
    }

    protected function emailLocale(mixed $notifiable): ?string
    {
        $locale = $notifiable instanceof HasLocalePreference ? $notifiable->preferredLocale() : null;

        return $locale !== null && $locale !== Languages::SOURCE && Languages::isLive($locale) ? $locale : null;
    }

    protected function translateEmail(Email $message): void
    {
        $locale = app()->getLocale();

        try {
            if ($locale === Languages::SOURCE || ! Languages::isLive($locale) || ! Mark::$protect) {
                $this->stripEmail($message);

                return;
            }

            $translator = app(HtmlTranslator::class);

            if (is_string($html = $message->getHtmlBody())) {
                $message->html($translator->translateDocument($html, $locale, ['context' => 'email']));
            }

            if (is_string($text = $message->getTextBody())) {
                $message->text(Mark::strip($text));
            }

            $subject = Mark::strip((string) $message->getSubject());
            $message->subject($subject);

            if ($subject !== '' && Catalog::has($subject)) {
                $message->subject(app(TranslationStore::class)->line($subject, $locale, 'email subject'));
            }
        } catch (Throwable $e) {
            report($e);
            $this->stripEmail($message);
        }
    }

    protected function stripEmail(Email $message): void
    {
        if (($subject = $message->getSubject()) !== null) {
            $message->subject(Mark::strip($subject));
        }

        if (is_string($html = $message->getHtmlBody())) {
            $message->html(Mark::strip($html));
        }

        if (is_string($text = $message->getTextBody())) {
            $message->text(Mark::strip($text));
        }
    }
}
