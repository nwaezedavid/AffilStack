<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips API keys, tokens, and other credentials out of every log record
 * before it reaches a handler (file, Slack, syslog, Papertrail...) — the
 * defense-in-depth half of keeping credentials from leaking "to the
 * public": even if an exception message, an HTTP client error, or a debug
 * dump ends up carrying a raw secret, it should never reach disk or a
 * third-party log sink in cleartext.
 *
 * Three layers, cheapest and most precise first:
 *  1. Exact match against every secret RedactsSecrets found loaded into
 *     config() under a credential-shaped key when this processor was built
 *     (once per log-manager boot) — self-maintaining as new integrations
 *     are added, since it never has to know a specific env var name.
 *  2. Key-name-based: inside array context (a log call's $context array),
 *     a value whose own array key looks like a credential is replaced
 *     wholesale, regardless of what shape the value itself takes.
 *  3. Pattern-based: well-known credential shapes (Bearer headers, common
 *     provider key prefixes, key=value pairs) inside any remaining string —
 *     for secrets this process never had in config(), e.g. one just
 *     decrypted from a stored integration's credentials column and used
 *     transiently.
 */
class SecretRedactionProcessor implements ProcessorInterface
{
    public const REDACTED = '[REDACTED]';

    /**
     * Shared with RedactsSecrets, which decides which config() values
     * qualify as "known secrets" using this same pattern.
     */
    public const SECRET_KEY_PATTERN = '/(api[_-]?key|apikey|secret|passwd|password|credential|private[_-]?key|access[_-]?key|client[_-]?secret|authorization)/i';

    /**
     * @param  string[]  $knownSecrets  Exact secret values to strip wherever found, verbatim.
     */
    public function __construct(protected array $knownSecrets = []) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactString($record->message),
            context: $this->redactValue($record->context),
        );
    }

    protected function redactValue(mixed $value, ?string $key = null): mixed
    {
        if (is_string($key) && $value !== '' && is_scalar($value) && preg_match(self::SECRET_KEY_PATTERN, $key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $k => $v) {
                $redacted[$k] = $this->redactValue($v, is_string($k) ? $k : null);
            }

            return $redacted;
        }

        if (is_string($value)) {
            return $this->redactString($value);
        }

        // Never risk calling __toString() on an object we don't own here —
        // it could recurse, throw, or be expensive. Monolog's own formatter
        // normalizes objects after processors run; leave it to that.
        return $value;
    }

    protected function redactString(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        foreach ($this->knownSecrets as $secret) {
            if ($secret !== '' && strlen($secret) >= 6 && str_contains($text, $secret)) {
                $text = str_replace($secret, self::REDACTED, $text);
            }
        }

        $text = preg_replace('/Bearer\s+[A-Za-z0-9\-_.=]+/i', 'Bearer '.self::REDACTED, $text) ?? $text;
        $text = preg_replace('/\b(sk|rk|pk)_(live|test)_[A-Za-z0-9]{10,}/', self::REDACTED, $text) ?? $text;
        $text = preg_replace('/\bsk-[A-Za-z0-9]{10,}/', self::REDACTED, $text) ?? $text;
        $text = preg_replace('/\bAKIA[0-9A-Z]{16}\b/', self::REDACTED, $text) ?? $text;

        $text = preg_replace_callback(
            '/((?:api[_-]?key|apikey|secret|passwd|password|token|access[_-]?token|client[_-]?secret)["\']?\s*(?:=>|[:=])\s*["\']?)([^"\'&,\s\}\]]{3,})/i',
            fn (array $m) => $m[1].self::REDACTED,
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '/([?&](?:api[_-]?key|apikey|secret|token|access[_-]?token|client[_-]?secret)=)([^&\s"\']+)/i',
            fn (array $m) => $m[1].self::REDACTED,
            $text
        ) ?? $text;

        return $text;
    }
}
