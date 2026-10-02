<?php

namespace App\Support\Analytics;

use App\Support\Countries;

/**
 * Agent 04: normalisation + SHA-256 hashing for advertising customer lists,
 * following each platform's published spec (checked Oct 2026):
 *
 * - Meta Custom Audiences (customer list): email trimmed and lowercased;
 *   phone as digits only including the country code, no "+", no leading
 *   zeros; country as a lowercase ISO-2 code; every value SHA-256, lowercase
 *   hex. https://developers.facebook.com/documentation/ads-commerce/marketing-api/audiences/guides/custom-audiences
 * - Google Ads Customer Match: email trimmed, lowercased, and for
 *   gmail.com/googlemail.com addresses the dots before the "@" removed;
 *   phone in E.164 WITH the "+"; hex SHA-256; Country left unhashed.
 *   https://support.google.com/google-ads/answer/7659867
 */
class AudienceHasher
{
    public static function sha256(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function normalizeEmailForGoogle(?string $email): ?string
    {
        $email = self::normalizeEmail($email);

        if ($email === null) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
        }

        return $local.'@'.$domain;
    }

    public static function metaEmailHash(?string $email): ?string
    {
        $email = self::normalizeEmail($email);

        return $email === null ? null : self::sha256($email);
    }

    public static function googleEmailHash(?string $email): ?string
    {
        $email = self::normalizeEmailForGoogle($email);

        return $email === null ? null : self::sha256($email);
    }

    /**
     * The full international number as digits only (country code first),
     * or null when it can't be determined confidently — a wrong country
     * code just produces a hash that matches nobody, so it's left out.
     */
    public static function phoneDigits(?string $phone, ?string $countryCode = null): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $international = str_starts_with($phone, '+') || str_starts_with($phone, '00');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($international) {
            $digits = str_starts_with($phone, '00') ? substr($digits, 2) : $digits;
        } else {
            $dial = preg_replace('/\D+/', '', (string) Countries::dialCode($countryCode));

            if ($dial === '') {
                return null;
            }

            $digits = $dial.ltrim($digits, '0');
        }

        $digits = ltrim($digits, '0');

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function metaPhoneHash(?string $phone, ?string $countryCode = null): ?string
    {
        $digits = self::phoneDigits($phone, $countryCode);

        return $digits === null ? null : self::sha256($digits);
    }

    public static function googlePhoneHash(?string $phone, ?string $countryCode = null): ?string
    {
        $digits = self::phoneDigits($phone, $countryCode);

        return $digits === null ? null : self::sha256('+'.$digits);
    }
}
