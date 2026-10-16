<?php

namespace App\Support\Translation;

use Illuminate\Contracts\Support\DeferringDisplayableValue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;

/**
 * Keeps customers' own data out of translation.
 *
 * Every Blade echo goes through Mark::e() (see AppServiceProvider). While
 * a page in another language is being rendered for the dashboard, the
 * partner portal or an email, a value that isn't one of AffilStack's own
 * interface strings (see Catalog) — a product name, an article, an email
 * address, a number — is wrapped in invisible markers. The translator
 * turns marked values into placeholders, so they're never sent to Claude,
 * never stored, and always shown exactly as they are; the markers are
 * stripped before anything leaves the server.
 *
 * The public website (no customer data) translates everything, so marking
 * is off there.
 */
final class Mark
{
    public const VALUE_OPEN = "\u{E000}";

    public const VALUE_CLOSE = "\u{E001}";

    public const RAW_OPEN = "\u{E002}";

    public const RAW_CLOSE = "\u{E003}";

    public const ALL = [self::VALUE_OPEN, self::VALUE_CLOSE, self::RAW_OPEN, self::RAW_CLOSE];

    /**
     * Whether values are being marked right now.
     */
    public static bool $protect = false;

    /**
     * Whether HTML values are AffilStack's own (an email's Markdown body,
     * whose values were already marked as it was written) rather than a
     * customer's.
     */
    public static bool $trustHtml = false;

    /**
     * {{ $value }}
     */
    public static function e(mixed $value, bool $doubleEncode = true): string
    {
        $escaped = e($value, $doubleEncode);

        if (! self::$protect || $escaped === '' || self::isTemplateHtml($value)) {
            return $escaped;
        }

        // App text that already marks its own values (see MailText).
        if (is_string($value) && (str_contains($value, self::VALUE_OPEN) || Catalog::has($value))) {
            return $escaped;
        }

        // HTML values (an HtmlString) are kept whole, like {!! !!}.
        if ($value instanceof Htmlable) {
            if (self::$trustHtml) {
                return $escaped;
            }

            return self::RAW_OPEN.$escaped.self::RAW_CLOSE;
        }

        return self::VALUE_OPEN.$escaped.self::VALUE_CLOSE;
    }

    /**
     * {!! $value !!}
     */
    public static function raw(mixed $value): mixed
    {
        if (! self::$protect || self::$trustHtml || self::isTemplateHtml($value)) {
            return $value;
        }

        $string = (string) $value;

        if ($string === '' || (is_string($value) && Catalog::has($value))) {
            return $value;
        }

        return self::RAW_OPEN.$string.self::RAW_CLOSE;
    }

    /**
     * Text from AffilStack's own code that should be translated even
     * though it's echoed as a value (flash messages, validation errors).
     */
    public static function text(?string $value): Htmlable
    {
        return new Translatable((string) $value);
    }

    /**
     * A flash message: translated when it's one of AffilStack's own fixed
     * messages; one that includes the customer's data ("Offer “Boots”
     * deleted") stays as it is, kept out of translation.
     */
    public static function flash(mixed $value): mixed
    {
        return is_string($value) && Catalog::has($value) ? new Translatable($value) : $value;
    }

    public static function strip(string $content): string
    {
        if (! preg_match('/[\x{E000}-\x{E003}]|\\\\u[eE]00[0-3]/u', $content)) {
            return $content;
        }

        $content = str_replace(self::ALL, '', $content);

        // The same markers inside JSON, where they're written as .
        return (string) preg_replace('/\\\\u[eE]00[0-3]/', '', $content);
    }

    public static function contains(string $content): bool
    {
        return (bool) preg_match('/[\x{E000}-\x{E003}]/u', $content);
    }

    /**
     * HTML that comes from AffilStack's own templates (component slots,
     * attribute bags, rendered views): its text is translatable and any
     * values inside it are already marked.
     */
    protected static function isTemplateHtml(mixed $value): bool
    {
        return $value instanceof ComponentSlot
            || $value instanceof ComponentAttributeBag
            || $value instanceof View
            || $value instanceof Translatable
            || $value instanceof DeferringDisplayableValue;
    }
}
