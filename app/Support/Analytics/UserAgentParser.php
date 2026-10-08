<?php

namespace App\Support\Analytics;

/**
 * Agent 04: dependency-free user-agent classification — device type,
 * browser family and OS family, which is all the insights need (no
 * versions, no fingerprinting). Same spirit as LinkCloakingService's
 * click classifier, extended with OS detection.
 */
class UserAgentParser
{
    /**
     * @return array{device_type: string, browser: string, os: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'device_type' => self::device($ua),
            'browser' => self::browser($ua),
            'os' => self::os($ua),
        ];
    }

    public static function device(string $ua): string
    {
        return match (true) {
            $ua === '' => 'unknown',
            (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $ua) => 'bot',
            (bool) preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    public static function browser(string $ua): string
    {
        return match (true) {
            $ua === '' => 'Unknown',
            (bool) preg_match('/edg(e|a|ios)?\//i', $ua) => 'Edge',
            (bool) preg_match('/opr\/|opera/i', $ua) => 'Opera',
            (bool) preg_match('/samsungbrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/ucbrowser/i', $ua) => 'UC Browser',
            (bool) preg_match('/fban|fbav|instagram/i', $ua) => 'In-app (Meta)',
            (bool) preg_match('/firefox|fxios/i', $ua) => 'Firefox',
            (bool) preg_match('/chrome|crios|chromium/i', $ua) => 'Chrome',
            (bool) preg_match('/safari/i', $ua) => 'Safari',
            default => 'Other',
        };
    }

    public static function os(string $ua): string
    {
        return match (true) {
            $ua === '' => 'Unknown',
            (bool) preg_match('/windows phone/i', $ua) => 'Windows Phone',
            (bool) preg_match('/windows/i', $ua) => 'Windows',
            (bool) preg_match('/iphone|ipad|ipod/i', $ua) => 'iOS',
            (bool) preg_match('/android/i', $ua) => 'Android',
            (bool) preg_match('/cros/i', $ua) => 'ChromeOS',
            (bool) preg_match('/mac os x|macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/linux/i', $ua) => 'Linux',
            default => 'Other',
        };
    }
}
