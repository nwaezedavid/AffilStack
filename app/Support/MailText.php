<?php

namespace App\Support;

use App\Support\Translation\Mark;

/**
 * Notification emails run every line through Markdown after Blade escapes
 * the HTML, so text written by someone else — a CRM reply from a stranger,
 * a contact-form message, a name — could still turn into a real link or a
 * remote image ([text](https://…), ![](…)) inside an email AffilStack sends.
 * Escaping the Markdown punctuation keeps it as the plain text it was.
 */
class MailText
{
    public static function escape(?string $value): string
    {
        // Only what's needed to stop links and images: Blade already turns
        // < and > into entities, and escaping more would leave visible
        // backslashes in the plain-text part of the email.
        $escaped = addcslashes((string) $value, '\\[]!');

        // In an email being translated, mark it as the recipient's data so
        // it's kept out of translation (see Mark).
        return Mark::$protect && $escaped !== '' ? Mark::VALUE_OPEN.$escaped.Mark::VALUE_CLOSE : $escaped;
    }
}
