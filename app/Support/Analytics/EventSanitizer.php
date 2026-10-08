<?php

namespace App\Support\Analytics;

use Illuminate\Support\Str;

/**
 * Agent 04: everything sent to window.affilstackTrack() is untrusted input
 * from a browser. Event names and property keys must be snake_case, values
 * are scalars clamped to a sane length, and anything that looks like
 * contact details (an email address, a phone-number-shaped value, or a key
 * like "email"/"phone") is dropped — the event tables never hold raw
 * personal contact data.
 */
class EventSanitizer
{
    public static function isValidName(mixed $name): bool
    {
        return is_string($name) && preg_match('/^[a-z][a-z0-9_]{1,59}$/', $name) === 1;
    }

    /**
     * @return array<string, string|int|float|bool>|null
     */
    public static function properties(mixed $properties): ?array
    {
        if (! is_array($properties)) {
            return null;
        }

        $blocked = (array) config('analytics.events.blocked_property_keys', []);
        $maxLength = (int) config('analytics.events.max_value_length', 200);
        $clean = [];

        foreach ($properties as $key => $value) {
            if (count($clean) >= (int) config('analytics.events.max_properties', 10)) {
                break;
            }

            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) !== 1 || in_array($key, $blocked, true)) {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $clean[$key] = is_float($value) && ! is_finite($value) ? 0 : $value;

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $value = trim(strip_tags($value));

            if ($value === '' || self::looksLikeContactDetails($value)) {
                continue;
            }

            $clean[$key] = Str::limit($value, $maxLength, '');
        }

        return $clean === [] ? null : $clean;
    }

    public static function looksLikeContactDetails(string $value): bool
    {
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value) === 1) {
            return true;
        }

        // 9+ digits, optionally with spaces/dashes/brackets and a leading
        // "+": the shape of a phone number, not of a plan id or a price.
        return preg_match('/^\+?[\d\s().-]{9,}$/', $value) === 1 && preg_match_all('/\d/', $value) >= 9;
    }

    public static function path(?string $path): string
    {
        $path = (string) (parse_url((string) $path, PHP_URL_PATH) ?? '');
        $path = ltrim(Str::limit($path, 200, ''), '/');

        return $path === '' ? '/' : $path;
    }
}
