<?php

namespace App\Services\Crm\Inbox;

/**
 * Cuts the quoted history off a reply so the Inbox shows only what the
 * contact actually wrote: "On … wrote:" headers (Gmail/Apple, in several
 * languages, even when wrapped over two lines), Outlook's "From:/Sent:"
 * block and "-----Original Message-----", "> " quoted lines, and a "-- "
 * signature delimiter.
 */
class QuotedTextStripper
{
    public static function strip(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $kept = [];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            $nextTwo = $trimmed.' '.trim($lines[$i + 1] ?? '');

            if (
                preg_match('/^-{2,}\s*(Original Message|Forwarded message)/i', $trimmed)
                || preg_match('/^_{5,}$/', $trimmed)
                || preg_match('/^(On|Le|Am|El|Em|Il|Op)\s.{4,200}\s(wrote|a écrit|schrieb|escribió|escreveu|ha scritto|schreef)\s?:$/iu', $trimmed)
                || (preg_match('/^(On|Le|Am|El|Em)\s/i', $trimmed) && preg_match('/\s(wrote|a écrit|schrieb|escribió|escreveu):$/iu', $nextTwo))
                || (preg_match('/^From:\s/i', $trimmed) && preg_match('/^(Sent|Date|To):\s/i', trim($lines[$i + 1] ?? '')))
                || $trimmed === '--'
            ) {
                break;
            }

            if (str_starts_with($trimmed, '>')) {
                continue;
            }

            $kept[] = rtrim($line);
        }

        $clean = trim(implode("\n", $kept));

        return $clean !== '' ? (preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean) : trim($text);
    }
}
