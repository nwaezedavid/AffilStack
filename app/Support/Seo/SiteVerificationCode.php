<?php

namespace App\Support\Seo;

/**
 * The value that goes inside a search engine's verification meta tag
 * (google-site-verification, msvalidate.01).
 *
 * Google's Site Verification API hands back the whole tag rather than just
 * its code, and people often paste the whole tag too — printing that inside
 * content="" breaks verification. This keeps only the code.
 */
final class SiteVerificationCode
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if (preg_match('/content\s*=\s*(["\'])(.*?)\1/i', html_entity_decode($value, ENT_QUOTES | ENT_HTML5), $matches)) {
            $value = trim($matches[2]);
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        return $value === '' ? null : $value;
    }
}
