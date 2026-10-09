<?php

namespace App\Support;

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
        return addcslashes((string) $value, '\\`*_[]()!<>#|~');
    }
}
