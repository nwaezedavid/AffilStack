<?php

namespace Tests\Support;

use App\Services\Crm\Imap\ImapException;
use App\Services\Crm\Imap\ImapTransport;

/**
 * A fake IMAP server for tests: every command the client writes is matched
 * against $handlers (regex => response text, where "{TAG}" is replaced with
 * the command's tag) and the response is queued for the client to read —
 * literals and all, byte for byte.
 */
class ScriptedImapTransport implements ImapTransport
{
    /** @var array<int, string> */
    public array $commands = [];

    public bool $tlsStarted = false;

    public bool $closed = false;

    public ?array $opened = null;

    protected string $buffer = '';

    protected string $pending = '';

    /**
     * @param  array<string, string|callable(string, string): string>  $handlers
     */
    public function __construct(
        public array $handlers,
        protected string $greeting = "* OK [CAPABILITY IMAP4rev1 STARTTLS AUTH=PLAIN] Fake IMAP ready\r\n",
        protected ?ImapException $openFailure = null,
    ) {}

    public function open(string $host, int $port, string $encryption, int $timeout): void
    {
        if ($this->openFailure) {
            throw $this->openFailure;
        }

        $this->opened = compact('host', 'port', 'encryption', 'timeout');
        $this->buffer .= $this->greeting;
    }

    public function startTls(): void
    {
        $this->tlsStarted = true;
    }

    public function readLine(): string
    {
        $pos = strpos($this->buffer, "\n");

        if ($pos === false) {
            throw new ImapException('Fake server has nothing more to say (unexpected read).', 'timeout');
        }

        $line = substr($this->buffer, 0, $pos + 1);
        $this->buffer = substr($this->buffer, $pos + 1);

        return $line;
    }

    public function read(int $bytes): string
    {
        $data = substr($this->buffer, 0, $bytes);
        $this->buffer = substr($this->buffer, $bytes);

        return $data;
    }

    public function write(string $data): void
    {
        $this->pending .= $data;

        while (($pos = strpos($this->pending, "\r\n")) !== false) {
            $line = substr($this->pending, 0, $pos);
            $this->pending = substr($this->pending, $pos + 2);
            $this->respond($line);
        }
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /** @var array<int, string> lines of the command being received */
    protected array $current = [];

    protected function respond(string $line): void
    {
        $this->commands[] = $line;

        if (preg_match('/^A\d{4} /', $line)) {
            $this->current = [$line];
        } else {
            $this->current[] = $line;
        }

        // A literal announcement ("… {12}") gets a continuation; the next
        // write completes the command.
        if (preg_match('/\{(\d+)\}$/', $line)) {
            $this->buffer .= "+ Ready for literal data\r\n";

            return;
        }

        $full = implode(' ', $this->current);
        [$tag] = explode(' ', $this->current[0], 2);

        foreach ($this->handlers as $pattern => $response) {
            if (preg_match($pattern, $full)) {
                $text = is_callable($response) ? $response($full, $tag) : $response;
                $this->buffer .= str_replace('{TAG}', $tag, $text);

                return;
            }
        }

        $this->buffer .= "{$tag} BAD Unexpected command in test script\r\n";
    }
}
