<?php

namespace App\Services\WordPress;

/**
 * Request signing shared by AffilStack and the AffilStack Connector plugin
 * (the plugin's class-affilstack-signature.php mirrors this exactly). Each
 * request carries the site id, a Unix timestamp and an HMAC-SHA256 of
 * "timestamp \n METHOD \n path \n sha256(body)" keyed with the site's
 * secret, so neither side ever sends the secret itself and a captured
 * request can't be replayed later or against another path.
 */
class ConnectorSignature
{
    public const SITE_HEADER = 'X-AffilStack-Site';

    public const TIMESTAMP_HEADER = 'X-AffilStack-Timestamp';

    public const SIGNATURE_HEADER = 'X-AffilStack-Signature';

    public static function sign(string $secret, int $timestamp, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', $timestamp."\n".strtoupper($method)."\n".static::normalizePath($path)."\n".hash('sha256', $body), $secret);
    }

    /**
     * @return array<string, string>
     */
    public static function headers(int $siteId, string $secret, string $method, string $path, string $body, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            self::SITE_HEADER => (string) $siteId,
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::SIGNATURE_HEADER => static::sign($secret, $timestamp, $method, $path, $body),
        ];
    }

    public static function verify(string $secret, string $timestamp, string $signature, string $method, string $path, string $body): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > (int) config('wordpress_plugin.signature_window_seconds', 300)) {
            return false;
        }

        return hash_equals(static::sign($secret, (int) $timestamp, $method, $path, $body), strtolower($signature));
    }

    /**
     * The path part only ("/api/wordpress/disconnect"), without query string
     * or trailing slash, so both sides agree whatever their URL rewriting.
     */
    public static function normalizePath(string $path): string
    {
        $path = (string) (parse_url($path, PHP_URL_PATH) ?? $path);

        return '/'.trim($path, '/');
    }
}
