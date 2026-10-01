<?php

namespace App\Services\Crm;

use App\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * A real SMTP handshake without sending anything: connect, EHLO, STARTTLS
 * (or implicit TLS on 465) and AUTH — exactly what a send would do first —
 * then QUIT. Separate class so tests can swap it out.
 */
class SmtpProbe
{
    /**
     * @param  array{host: string, port: int|string, username: string, password: string, encryption?: string|null}  $settings
     *
     * @throws RuntimeException with the server's own error text
     */
    public function probe(array $settings): void
    {
        try {
            OutboundUrlGuard::resolveSafeHost((string) $settings['host']);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        $transport = Mail::createSymfonyTransport([
            'transport' => 'smtp',
            'timeout' => 15,
            'host' => $settings['host'],
            'port' => (int) $settings['port'],
            'username' => $settings['username'],
            'password' => $settings['password'],
            'scheme' => ($settings['encryption'] ?? null) === 'ssl' || (int) $settings['port'] === 465 ? 'smtps' : 'smtp',
        ]);

        try {
            if ($transport instanceof SmtpTransport) {
                $transport->start();
                $transport->stop();
            }
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }
}
