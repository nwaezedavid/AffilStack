<?php

namespace App\Support\Analytics;

/**
 * Agent 04: IP addresses are never stored raw by the analytics system —
 * only as a keyed hash (salted with the app key, so it can't be reversed by
 * brute-forcing the IPv4 space without that key) or truncated to the
 * network (/24 for IPv4, /48 for IPv6).
 */
class IpPrivacy
{
    public static function hash(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    public static function truncate(?string $ip): ?string
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $ip);
            $octets[3] = '0';

            return implode('.', $octets);
        }

        $packed = inet_pton($ip);

        return inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10)) ?: null;
    }

    public static function isPublic(?string $ip): bool
    {
        return (bool) ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
    }
}
