<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Stops customer-supplied URLs (webhook endpoints, Zapier REST-hook
 * targets) from pointing the server at itself or its private network.
 * Webhook deliveries store and display the response body, so without this
 * a customer could read internal services (the database port, local admin
 * endpoints, cloud metadata) through their own delivery log.
 *
 * Checked twice: when the URL is saved (a friendly validation error) and
 * again at every delivery, where the vetted IP is pinned for the actual
 * connection so a DNS record can't be switched to 127.0.0.1 in between.
 */
class OutboundUrlGuard
{
    /** @var (callable(string): array<int, string>)|null */
    protected static $resolver = null;

    /**
     * Swap DNS resolution (tests) — null restores the real resolver.
     *
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public static function resolveUsing(?callable $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * @return string|null why the URL is refused, or null when it's safe
     */
    public static function problem(string $url): ?string
    {
        try {
            static::resolveSafeIp($url);

            return null;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return array{host: string, port: int, ip: string}
     *
     * @throws InvalidArgumentException
     */
    public static function resolveSafeIp(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('The URL must start with http:// or https://.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('The URL must not contain a username or password.');
        }

        return [
            'host' => $host,
            'port' => (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)),
            'ip' => static::resolveSafeHost($host),
        ];
    }

    /**
     * A bare hostname (e.g. a customer's SMTP server) that resolves only to
     * public addresses.
     *
     * @throws InvalidArgumentException
     */
    public static function resolveSafeHost(string $host): string
    {
        $host = strtolower(trim($host, " \t\n\r\0\x0B[]"));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException('The address must be a public internet host.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : static::resolve($host);

        if ($ips === []) {
            throw new InvalidArgumentException("The host {$host} could not be resolved.");
        }

        foreach ($ips as $ip) {
            if (! static::isPublicIp($ip)) {
                throw new InvalidArgumentException('The address must be a public internet host.');
            }
        }

        return $ips[0];
    }

    public static function isPublicIp(string $ip): bool
    {
        // IPv4-mapped IPv6 (::ffff:127.0.0.1) is judged as the IPv4 it wraps.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $mapped)) {
            $ip = $mapped[1];
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // IPv6 prefixes that embed an IPv4 address (NAT64, 6to4) could reach
        // a private IPv4 host through a translator — refuse them outright.
        $lower = strtolower($ip);

        if (str_starts_with($lower, '64:ff9b:') || str_starts_with($lower, '2002:') || str_starts_with($lower, '::ffff:')) {
            return false;
        }

        // Carrier-grade NAT (100.64.0.0/10) isn't covered by PHP's flags.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            if ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected static function resolve(string $host): array
    {
        if (static::$resolver !== null) {
            return array_values((static::$resolver)($host));
        }

        $ips = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
