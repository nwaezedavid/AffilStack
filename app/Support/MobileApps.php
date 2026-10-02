<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The AffilStack mobile apps' store links, set from Content → Mobile Apps
 * (App\Filament\Pages\MobileAppSettings). Nothing about the apps shows
 * anywhere until a link is saved (or "Coming soon" is switched on), so the
 * owner can ship the feature today and just paste the links on launch day:
 * the homepage apps band, the site footer, the customer dashboard card,
 * the /app smart link and the iOS Smart App Banner all light up at once.
 */
class MobileApps
{
    public static function playStoreUrl(): ?string
    {
        return static::validUrl(SiteSetting::get('mobile_app_play_url'), ['play.google.com']);
    }

    public static function appStoreUrl(): ?string
    {
        return static::validUrl(SiteSetting::get('mobile_app_store_url'), ['apps.apple.com', 'itunes.apple.com']);
    }

    /**
     * The numeric App Store ID (".../id1234567890"), which Safari's Smart
     * App Banner needs — null when no App Store link is set or it has none.
     */
    public static function appStoreId(): ?string
    {
        $url = static::appStoreUrl();

        if ($url && preg_match('~/id(\d{5,})~', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function hasAnyLink(): bool
    {
        return static::playStoreUrl() !== null || static::appStoreUrl() !== null;
    }

    public static function showsComingSoon(): bool
    {
        return SiteSetting::flag('mobile_app_coming_soon', false);
    }

    /**
     * Whether the apps band / footer badges / dashboard card render at all.
     */
    public static function isVisible(): bool
    {
        return static::hasAnyLink() || static::showsComingSoon();
    }

    public static function appName(): string
    {
        $name = trim((string) SiteSetting::get('mobile_app_name', ''));

        return $name !== '' ? $name : SiteSetting::get('site_name', 'AffilStack').' for iOS & Android';
    }

    public static function pitch(): string
    {
        $pitch = trim((string) SiteSetting::get('mobile_app_pitch', ''));

        return $pitch !== '' ? $pitch : 'Research products, follow up with buyers and check your earnings from anywhere — your whole affiliate business in your pocket.';
    }

    public static function screenshotUrl(): ?string
    {
        $path = SiteSetting::get('mobile_app_screenshot_path');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function screenshotAlt(): string
    {
        $alt = trim((string) SiteSetting::get('mobile_app_screenshot_alt', ''));

        return $alt !== '' ? $alt : static::appName().' app screenshot';
    }

    /**
     * Which store the /app smart link should send a visitor to, from their
     * browser's user agent: an Android device → Google Play, an iPhone /
     * iPad / iPod → the App Store, anything else (or a store with no link
     * yet) → null, meaning "show them the homepage apps section instead".
     */
    public static function storeUrlForUserAgent(?string $userAgent): ?string
    {
        $userAgent = (string) $userAgent;

        if (preg_match('/android/i', $userAgent)) {
            return static::playStoreUrl();
        }

        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            return static::appStoreUrl();
        }

        return null;
    }

    /**
     * @param  array<int, string>  $allowedHosts
     */
    protected static function validUrl(mixed $value, array $allowedHosts): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || ! preg_match('#^https://#i', $url)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, $allowedHosts, true) ? $url : null;
    }
}
