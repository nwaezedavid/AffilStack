<?php

namespace App\Services\Crm;

use App\Support\OutboundUrlGuard;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * An SMTP transport for a customer's own mail server that connects to the
 * IP address OutboundUrlGuard vetted, not to whatever the hostname resolves
 * to a moment later. Without the pin a DNS record could answer "public" to
 * the guard and "127.0.0.1" to the socket (DNS rebinding) and point the
 * server at its own private network. TLS still verifies the certificate
 * against the real hostname through peer_name / SNI.
 */
class PinnedSmtpTransport
{
    /**
     * @param  array{host: string, port: int|string, username?: string|null, password?: string|null, encryption?: string|null}  $settings
     *
     * @throws \InvalidArgumentException when the host isn't a public internet host
     */
    public static function make(array $settings, int $timeout = 15): EsmtpTransport
    {
        $host = strtolower(trim((string) $settings['host']));
        $ip = OutboundUrlGuard::resolveSafeHost($host);
        $port = (int) $settings['port'];
        $implicitTls = ($settings['encryption'] ?? null) === 'ssl' || $port === 465;

        $transport = new EsmtpTransport(str_contains($ip, ':') ? "[{$ip}]" : $ip, $port, $implicitTls);
        $transport->setUsername((string) ($settings['username'] ?? ''));
        $transport->setPassword((string) ($settings['password'] ?? ''));
        $transport->setLocalDomain(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            $stream->setTimeout($timeout);
            $stream->setStreamOptions(['ssl' => [
                'peer_name' => $host,
                'SNI_enabled' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]]);
        }

        return $transport;
    }
}
