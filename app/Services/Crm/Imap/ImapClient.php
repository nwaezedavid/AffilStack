<?php

namespace App\Services\Crm\Imap;

use Illuminate\Support\Carbon;

/**
 * A small, pure-PHP IMAP4rev1 client — just what CRM reply detection needs:
 * connect (implicit TLS or STARTTLS), LOGIN, CAPABILITY, SELECT, UID SEARCH,
 * UID FETCH of headers + BODYSTRUCTURE + one text part, LOGOUT. Literals
 * ({n}) are handled in both directions; every byte goes through an
 * ImapTransport so tests can script the server's side.
 */
class ImapClient
{
    protected int $tagCounter = 0;

    /** @var array<int, string> */
    protected array $capabilities = [];

    protected bool $connected = false;

    public function __construct(protected ImapTransport $transport) {}

    /**
     * @throws ImapException
     */
    public function connect(string $host, int $port, string $encryption = 'ssl', int $timeout = 15): void
    {
        $this->transport->open($host, $port, $encryption, $timeout);
        $this->connected = true;

        $greeting = $this->transport->readLine();

        if (! preg_match('/^\* (OK|PREAUTH)/i', $greeting)) {
            throw new ImapException('Unexpected greeting from the mail server: '.trim($greeting), 'protocol');
        }

        $this->captureCapabilities($greeting);

        if ($encryption === 'tls') {
            if (! in_array('STARTTLS', $this->capability(), true)) {
                throw new ImapException('This server does not offer STARTTLS on that port — use SSL/TLS on port 993 instead.', 'tls');
            }

            $this->command('STARTTLS');
            $this->transport->startTls();
            $this->capabilities = [];
        }
    }

    /**
     * @return array<int, string>
     */
    public function capability(): array
    {
        if ($this->capabilities === []) {
            foreach ($this->command('CAPABILITY')['untagged'] as $response) {
                $this->captureCapabilities('* '.$response['text']);
            }
        }

        return $this->capabilities;
    }

    /**
     * @throws ImapException kind "auth" when the server rejects the credentials
     */
    public function login(string $username, string $password): void
    {
        if (in_array('LOGINDISABLED', $this->capabilities, true)) {
            throw new ImapException('The server refuses plain logins on this connection — use SSL/TLS (port 993).', 'tls');
        }

        try {
            $result = $this->command(['LOGIN ', $this->argument($username), ' ', $this->argument($password)]);
        } catch (ImapException $e) {
            throw new ImapException($e->getMessage(), $e->kind === 'no' ? 'auth' : $e->kind, $e);
        }

        $this->captureCapabilities('* '.$result['status_text']);
    }

    /**
     * @return array{exists: int, uidvalidity: int|null, uidnext: int|null}
     */
    public function select(string $mailbox = 'INBOX'): array
    {
        $result = $this->command(['SELECT ', $this->argument($mailbox)]);
        $info = ['exists' => 0, 'uidvalidity' => null, 'uidnext' => null];

        foreach ($result['untagged'] as $response) {
            $text = $response['text'];

            if (preg_match('/^(\d+) EXISTS/i', $text, $m)) {
                $info['exists'] = (int) $m[1];
            }

            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $text, $m)) {
                $info['uidvalidity'] = (int) $m[1];
            }

            if (preg_match('/\[UIDNEXT (\d+)\]/i', $text, $m)) {
                $info['uidnext'] = (int) $m[1];
            }
        }

        return $info;
    }

    /**
     * UIDs of messages after $lastUid (or, with no $lastUid, since $since).
     *
     * @return array<int, int>
     */
    public function searchNew(?int $lastUid, Carbon $since): array
    {
        $criteria = $lastUid
            ? 'UID '.($lastUid + 1).':*'
            : 'SINCE '.$since->format('d-M-Y');

        $uids = [];

        foreach ($this->command('UID SEARCH '.$criteria)['untagged'] as $response) {
            if (preg_match('/^SEARCH\b(.*)$/i', $response['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $uid) {
                    if (ctype_digit($uid)) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }

        // "n:*" always matches the newest message even when its UID < n.
        $uids = array_values(array_unique(array_filter($uids, fn (int $uid) => $lastUid === null || $uid > $lastUid)));
        sort($uids);

        return $uids;
    }

    /**
     * Headers, structure and the readable text body of each UID.
     *
     * @param  array<int, int>  $uids
     * @return array<int, InboundEmail>
     */
    public function fetchMessages(array $uids, int $maxBodyBytes = 65536): array
    {
        if ($uids === []) {
            return [];
        }

        $result = $this->command('UID FETCH '.implode(',', $uids).' (UID RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)])');
        $messages = [];

        foreach ($result['untagged'] as $response) {
            $data = $this->fetchData($response);

            if ($data === null || ! isset($data['UID'])) {
                continue;
            }

            $uid = (int) $data['UID'];
            $rawHeaders = '';

            foreach ($data as $key => $value) {
                if (str_starts_with($key, 'BODY[HEADER')) {
                    $rawHeaders = (string) $value;
                }
            }

            $headers = MimeDecoder::parseHeaders($rawHeaders);
            $part = is_array($data['BODYSTRUCTURE'] ?? null) ? BodyStructure::textPart($data['BODYSTRUCTURE']) : null;
            $text = $part ? $this->fetchText($uid, $part, $maxBodyBytes) : '';

            $messages[] = InboundEmail::fromHeaders((string) $uid, $headers, $text);
        }

        return $messages;
    }

    /**
     * @param  array{part: string, subtype: string, charset: string|null, encoding: string|null}  $part
     */
    protected function fetchText(int $uid, array $part, int $maxBodyBytes): string
    {
        $result = $this->command("UID FETCH {$uid} (BODY.PEEK[{$part['part']}]<0.{$maxBodyBytes}>)");

        foreach ($result['untagged'] as $response) {
            $data = $this->fetchData($response);

            foreach ($data ?? [] as $key => $value) {
                if (str_starts_with($key, 'BODY[') && is_string($value)) {
                    $text = MimeDecoder::toUtf8(MimeDecoder::decodeTransfer($value, $part['encoding']), $part['charset']);

                    return $part['subtype'] === 'html' ? MimeDecoder::htmlToText($text) : trim($text);
                }
            }
        }

        return '';
    }

    public function logout(): void
    {
        if (! $this->connected) {
            return;
        }

        try {
            $this->command('LOGOUT');
        } catch (ImapException) {
            // The server may drop the line before the tagged OK — fine.
        } finally {
            $this->transport->close();
            $this->connected = false;
        }
    }

    /**
     * Sends one tagged command and collects every response up to its
     * tagged completion. $command is either a plain string or segments in
     * which ImapLiteral objects are sent as synchronising literals.
     *
     * @param  string|array<int, string|ImapLiteral>  $command
     * @return array{status_text: string, untagged: array<int, array{text: string, literals: array<int, string>}>}
     *
     * @throws ImapException kind "no"/"bad" when the server refuses the command
     */
    public function command(string|array $command): array
    {
        $tag = 'A'.str_pad((string) ++$this->tagCounter, 4, '0', STR_PAD_LEFT);
        $buffer = $tag.' ';

        foreach ((array) $command as $segment) {
            if ($segment instanceof ImapLiteral) {
                $this->transport->write($buffer.'{'.strlen($segment->value)."}\r\n");
                $this->expectContinuation();
                $buffer = $segment->value;
            } else {
                $buffer .= $segment;
            }
        }

        $this->transport->write($buffer."\r\n");

        $untagged = [];

        while (true) {
            $response = $this->readResponse();
            $text = $response['text'];

            if (str_starts_with($text, $tag.' ')) {
                $rest = substr($text, strlen($tag) + 1);
                [$status, $statusText] = array_pad(explode(' ', $rest, 2), 2, '');
                $status = strtoupper($status);

                if ($status !== 'OK') {
                    throw new ImapException(trim($statusText) ?: "The server answered {$status}.", strtolower($status) === 'no' ? 'no' : 'bad');
                }

                return ['status_text' => $statusText, 'untagged' => $untagged];
            }

            if (str_starts_with($text, '* ')) {
                if (preg_match('/^\* BYE\b/i', $text) && ! str_starts_with(strtoupper(implode('', array_filter((array) $command, 'is_string'))), 'LOGOUT')) {
                    throw new ImapException('The server closed the session: '.substr($text, 6), 'connect');
                }

                $untagged[] = ['text' => substr($text, 2), 'literals' => $response['literals']];
            }
        }
    }

    /**
     * Reads one logical response: a line plus any {n} literals it
     * announces (each read as raw bytes and swapped for a "\0i\0" marker
     * that ImapParser resolves), continuing until a line ends without one.
     *
     * @return array{text: string, literals: array<int, string>}
     */
    protected function readResponse(): array
    {
        $text = '';
        $literals = [];

        while (true) {
            $line = $this->transport->readLine();

            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $m)) {
                $text .= substr($line, 0, -strlen($m[0]))."\0".count($literals)."\0";
                $literals[] = $this->transport->read((int) $m[1]);

                continue;
            }

            $text .= rtrim($line, "\r\n");

            return ['text' => $text, 'literals' => $literals];
        }
    }

    protected function expectContinuation(): void
    {
        $line = $this->transport->readLine();

        if (! str_starts_with($line, '+')) {
            throw new ImapException('The server refused the command: '.trim($line), 'no');
        }
    }

    /**
     * "n FETCH (KEY value KEY value …)" → ['KEY' => value, …].
     *
     * @param  array{text: string, literals: array<int, string>}  $response
     * @return array<string, mixed>|null
     */
    protected function fetchData(array $response): ?array
    {
        $tokens = ImapParser::parse($response['text'], $response['literals']);

        if (count($tokens) < 3 || strtoupper((string) $tokens[1]) !== 'FETCH' || ! is_array($tokens[2])) {
            return null;
        }

        $data = [];
        $items = $tokens[2];

        for ($i = 0; $i + 1 < count($items); $i += 2) {
            $data[strtoupper((string) $items[$i])] = $items[$i + 1];
        }

        return $data;
    }

    /**
     * A quoted string when safe, otherwise a literal (non-ASCII or CR/LF).
     */
    protected function argument(string $value): string|ImapLiteral
    {
        if ($value === '' || preg_match('/^[\x20-\x7E]{1,500}$/', $value)) {
            return '"'.addcslashes($value, '"\\').'"';
        }

        return new ImapLiteral($value);
    }

    protected function captureCapabilities(string $line): void
    {
        if (preg_match('/\[?CAPABILITY ([^\]\r\n]+)/i', $line, $m)) {
            $this->capabilities = array_map('strtoupper', preg_split('/\s+/', trim($m[1])) ?: []);
        }
    }
}
