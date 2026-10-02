<?php

namespace App\Services\Crm\Imap;

/**
 * The small slice of MIME a reply detector needs: RFC 5322 header parsing
 * (with folding and RFC 2047 encoded-words), Content-Transfer-Encoding
 * (quoted-printable / base64), charset conversion to UTF-8, address and
 * Message-ID extraction, a basic HTML → text fallback, and a full raw
 * message → text body walk for when a server hands back the whole message.
 */
class MimeDecoder
{
    /**
     * @return array<string, string> lower-cased header name => decoded value (first occurrence wins)
     */
    public static function parseHeaders(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $raw) ?? $raw;
        $headers = [];

        foreach (explode("\n", $unfolded) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));

            if ($name === '' || isset($headers[$name])) {
                continue;
            }

            $headers[$name] = trim(self::decodeHeader(trim($value)));
        }

        return $headers;
    }

    /**
     * Decodes RFC 2047 encoded-words ("=?UTF-8?B?...?=", "=?ISO-8859-1?Q?...?=").
     */
    public static function decodeHeader(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return self::toUtf8($value, 'UTF-8');
        }

        // Whitespace between two adjacent encoded-words is not displayed.
        $value = preg_replace('/(=\?[^?]+\?[BbQq]\?[^?]*\?=)\s+(?==\?)/', '$1', $value) ?? $value;

        return preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function (array $m): string {
            $charset = $m[1];
            $text = strtoupper($m[2]) === 'B'
                ? (string) base64_decode($m[3], false)
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));

            return self::toUtf8($text, $charset);
        }, $value) ?? $value;
    }

    public static function decodeTransfer(string $body, ?string $encoding): string
    {
        return match (strtolower(trim((string) $encoding))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Converts $text from $charset to valid UTF-8, never failing: unknown
     * charsets fall back to Windows-1252 (the usual mislabel) and any
     * remaining invalid bytes are scrubbed.
     */
    public static function toUtf8(string $text, ?string $charset): string
    {
        $charset = strtoupper(trim((string) $charset, " \t\"'"));

        if ($charset === '' || $charset === 'US-ASCII' || $charset === 'ASCII') {
            $charset = 'UTF-8';
        }

        if ($charset === 'UTF-8' || $charset === 'UTF8') {
            return mb_check_encoding($text, 'UTF-8') ? $text : self::fromLegacy($text);
        }

        $aliases = ['LATIN1' => 'ISO-8859-1', 'CP1252' => 'Windows-1252', 'WINDOWS-1252' => 'Windows-1252', 'GB2312' => 'GBK'];
        $charset = $aliases[$charset] ?? $charset;

        try {
            if (in_array(strtolower($charset), array_map('strtolower', mb_list_encodings()), true)) {
                return mb_scrub(mb_convert_encoding($text, 'UTF-8', $charset), 'UTF-8');
            }
        } catch (\ValueError) {
            // Fall through to iconv.
        }

        $converted = @iconv($charset, 'UTF-8//IGNORE', $text);

        return $converted !== false ? mb_scrub($converted, 'UTF-8') : self::fromLegacy($text);
    }

    protected static function fromLegacy(string $text): string
    {
        return mb_scrub(mb_convert_encoding($text, 'UTF-8', 'Windows-1252'), 'UTF-8');
    }

    /**
     * "Ada Obi <ada@example.com>" → ['email' => 'ada@example.com', 'name' => 'Ada Obi'].
     *
     * @return array{email: string, name: string|null}
     */
    public static function parseAddress(string $value): array
    {
        if (preg_match('/^(.*)<([^>]+)>/', $value, $m)) {
            $name = trim(trim($m[1]), '"\' ');

            return ['email' => strtolower(trim($m[2])), 'name' => $name !== '' ? $name : null];
        }

        if (preg_match('/[^\s<>"]+@[^\s<>"]+/', $value, $m)) {
            return ['email' => strtolower($m[0]), 'name' => null];
        }

        return ['email' => strtolower(trim($value)), 'name' => null];
    }

    /**
     * Every "<id@host>" in a Message-ID / In-Reply-To / References value.
     *
     * @return array<int, string>
     */
    public static function messageIds(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        preg_match_all('/<[^<>\s]+>/', $value, $m);

        return array_values(array_unique($m[0]));
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|blockquote)>#i', "\n", $html) ?? $html;
        $text = str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * Content-Type header → ['type' => 'text/plain', 'charset' => 'utf-8', 'boundary' => ...].
     *
     * @return array<string, string>
     */
    public static function contentType(?string $value): array
    {
        $parts = array_map('trim', explode(';', (string) $value));
        $result = ['type' => strtolower($parts[0] ?: 'text/plain')];

        foreach (array_slice($parts, 1) as $param) {
            if (str_contains($param, '=')) {
                [$key, $val] = explode('=', $param, 2);
                $result[strtolower(trim($key))] = trim(trim($val), '"');
            }
        }

        return $result;
    }

    /**
     * A whole raw RFC 822 message → its readable text body (text/plain
     * preferred, text/html converted otherwise), walking nested multiparts.
     */
    public static function textFromRawMessage(string $raw): string
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$head, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');
        $headers = self::parseHeaders($head);
        $type = self::contentType($headers['content-type'] ?? 'text/plain');

        if (str_starts_with($type['type'], 'multipart/') && isset($type['boundary'])) {
            $plain = null;
            $html = null;

            foreach (explode('--'.$type['boundary'], $body) as $section) {
                $section = ltrim($section, "\n");

                if ($section === '' || str_starts_with($section, '--')) {
                    continue;
                }

                [$partHead] = explode("\n\n", $section, 2);
                $partType = self::contentType(self::parseHeaders($partHead)['content-type'] ?? 'text/plain')['type'];

                if (str_starts_with($partType, 'multipart/')) {
                    $plain ??= self::textFromRawMessage($section);
                } elseif ($partType === 'text/plain') {
                    $plain ??= self::textFromRawMessage($section);
                } elseif ($partType === 'text/html') {
                    $html ??= self::textFromRawMessage($section);
                }
            }

            return trim($plain ?? $html ?? '');
        }

        $text = self::toUtf8(self::decodeTransfer($body, $headers['content-transfer-encoding'] ?? null), $type['charset'] ?? 'UTF-8');

        return $type['type'] === 'text/html' ? self::htmlToText($text) : trim($text);
    }
}
