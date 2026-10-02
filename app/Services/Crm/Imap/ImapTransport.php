<?php

namespace App\Services\Crm\Imap;

use Illuminate\Container\Attributes\Bind;

/**
 * The byte pipe ImapClient talks through — a real TLS socket in production
 * (StreamImapTransport), a scripted conversation in tests
 * (app()->instance(ImapTransport::class, …)).
 */
#[Bind(StreamImapTransport::class)]
interface ImapTransport
{
    /**
     * @param  string  $encryption  'ssl' (implicit TLS, port 993), 'tls' (STARTTLS, port 143) or 'none'
     *
     * @throws ImapException
     */
    public function open(string $host, int $port, string $encryption, int $timeout): void;

    /**
     * Upgrades the open plain connection to TLS (after a STARTTLS command).
     *
     * @throws ImapException
     */
    public function startTls(): void;

    /**
     * One line including its trailing CRLF.
     *
     * @throws ImapException
     */
    public function readLine(): string;

    /**
     * Exactly $bytes bytes (an IMAP literal).
     *
     * @throws ImapException
     */
    public function read(int $bytes): string;

    /**
     * @throws ImapException
     */
    public function write(string $data): void;

    public function close(): void;
}
