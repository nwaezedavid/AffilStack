<?php

namespace App\Services\Crm\Imap;

use App\Support\OutboundUrlGuard;
use InvalidArgumentException;

/**
 * A real IMAP socket via stream_socket_client — PHP 8.4 has no ext-imap and
 * the project takes no new dependencies. The host is resolved through
 * OutboundUrlGuard and the vetted IP pinned for the connection (so a
 * customer can't point it at the server's private network), while TLS still
 * verifies the certificate against the real hostname (SNI / peer_name).
 */
class StreamImapTransport implements ImapTransport
{
    /** @var resource|null */
    protected $stream = null;

    protected string $host = '';

    public function open(string $host, int $port, string $encryption, int $timeout): void
    {
        try {
            $ip = OutboundUrlGuard::resolveSafeHost($host);
        } catch (InvalidArgumentException $e) {
            throw new ImapException($e->getMessage(), 'host', $e);
        }

        $this->host = $host;
        $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';

        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'SNI_enabled' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]]);

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client("{$scheme}://{$address}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (! $stream) {
            $kind = str_contains(strtolower($errstr), 'certificate') || str_contains(strtolower($errstr), 'ssl') ? 'tls' : 'connect';

            throw new ImapException("Could not connect to {$host}:{$port} — {$errstr} ({$errno})", $kind);
        }

        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;
    }

    public function startTls(): void
    {
        $ok = @stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        if ($ok !== true) {
            throw new ImapException("TLS negotiation with {$this->host} failed.", 'tls');
        }
    }

    public function readLine(): string
    {
        $this->ensureOpen();
        $line = fgets($this->stream, 65536);

        if ($line === false) {
            $this->throwReadFailure();
        }

        return $line;
    }

    public function read(int $bytes): string
    {
        $this->ensureOpen();
        $data = '';

        while (strlen($data) < $bytes) {
            $chunk = fread($this->stream, min(8192, $bytes - strlen($data)));

            if ($chunk === false || $chunk === '') {
                $this->throwReadFailure();
            }

            $data .= $chunk;
        }

        return $data;
    }

    public function write(string $data): void
    {
        $this->ensureOpen();

        if (@fwrite($this->stream, $data) === false) {
            throw new ImapException('Could not write to the mail server.', 'connect');
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    protected function ensureOpen(): void
    {
        if (! is_resource($this->stream)) {
            throw new ImapException('The IMAP connection is not open.', 'connect');
        }
    }

    protected function throwReadFailure(): never
    {
        $meta = stream_get_meta_data($this->stream);

        throw new ImapException(
            ($meta['timed_out'] ?? false) ? 'The mail server stopped responding (timed out).' : 'The mail server closed the connection.',
            ($meta['timed_out'] ?? false) ? 'timeout' : 'connect',
        );
    }
}
