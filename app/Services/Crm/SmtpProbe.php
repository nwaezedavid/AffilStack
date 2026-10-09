<?php

namespace App\Services\Crm;

use InvalidArgumentException;
use RuntimeException;
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
            $transport = PinnedSmtpTransport::make($settings);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        try {
            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }
}
