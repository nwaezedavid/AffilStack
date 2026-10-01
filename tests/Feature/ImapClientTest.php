<?php

namespace Tests\Feature;

use App\Services\Crm\Imap\BodyStructure;
use App\Services\Crm\Imap\ImapClient;
use App\Services\Crm\Imap\ImapException;
use App\Services\Crm\Imap\ImapParser;
use App\Services\Crm\Imap\MimeDecoder;
use Illuminate\Support\Carbon;
use Tests\Support\ScriptedImapTransport;
use Tests\TestCase;

/**
 * The pure-PHP IMAP client behind CRM reply detection, driven against a
 * scripted fake server: literals, STARTTLS, quoting, BODYSTRUCTURE
 * navigation, transfer encodings and charsets.
 */
class ImapClientTest extends TestCase
{
    /**
     * @param  array<string, string|callable>  $extra
     */
    protected function server(array $extra = []): ScriptedImapTransport
    {
        return new ScriptedImapTransport($extra + [
            '/ LOGIN /' => "{TAG} OK [CAPABILITY IMAP4rev1 IDLE] Logged in\r\n",
            '/ SELECT /' => "* 3 EXISTS\r\n* OK [UIDVALIDITY 1700000001] UIDs valid\r\n* OK [UIDNEXT 58] Predicted next UID\r\n{TAG} OK [READ-WRITE] Select completed\r\n",
            '/ LOGOUT/' => "* BYE Logging out\r\n{TAG} OK Logout completed\r\n",
        ]);
    }

    public function test_it_logs_in_selects_the_inbox_and_reads_uid_validity(): void
    {
        $server = $this->server();
        $client = new ImapClient($server);

        $client->connect('imap.example.com', 993, 'ssl');
        $client->login('ada@example.com', 'secret');
        $info = $client->select();
        $client->logout();

        $this->assertSame(['exists' => 3, 'uidvalidity' => 1700000001, 'uidnext' => 58], $info);
        $this->assertSame('A0001 LOGIN "ada@example.com" "secret"', $server->commands[0]);
        $this->assertSame('A0002 SELECT "INBOX"', $server->commands[1]);
        $this->assertTrue($server->closed);
    }

    public function test_passwords_are_escaped_and_non_ascii_ones_sent_as_literals(): void
    {
        $server = $this->server();
        $client = new ImapClient($server);
        $client->connect('imap.example.com', 993);
        $client->login('ada', 'pa"ss\\word');

        $this->assertSame('A0001 LOGIN "ada" "pa\\"ss\\\\word"', $server->commands[0]);

        $server = $this->server();
        $client = new ImapClient($server);
        $client->connect('imap.example.com', 993);
        $client->login('ada', 'pässwörd');

        $this->assertSame('A0001 LOGIN "ada" {10}', $server->commands[0]);
        $this->assertSame('pässwörd', $server->commands[1]);
    }

    public function test_starttls_is_negotiated_on_port_143(): void
    {
        $server = $this->server(['/ STARTTLS/' => "{TAG} OK Begin TLS negotiation now\r\n"]);
        $client = new ImapClient($server);

        $client->connect('imap.example.com', 143, 'tls');

        $this->assertTrue($server->tlsStarted);
        $this->assertSame('A0001 STARTTLS', $server->commands[0]);
    }

    public function test_a_rejected_login_is_reported_as_an_auth_failure(): void
    {
        $server = new ScriptedImapTransport([
            '/ LOGIN /' => "{TAG} NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)\r\n",
        ]);
        $client = new ImapClient($server);
        $client->connect('imap.gmail.com', 993);

        try {
            $client->login('ada@gmail.com', 'wrong');
            $this->fail('Expected an auth failure.');
        } catch (ImapException $e) {
            $this->assertSame('auth', $e->kind);
            $this->assertStringContainsString('AUTHENTICATIONFAILED', $e->getMessage());
        }
    }

    public function test_search_returns_only_uids_after_the_last_seen_one(): void
    {
        $server = $this->server([
            '/UID SEARCH UID 58:\*/' => "* SEARCH 57\r\n{TAG} OK Search completed\r\n",
            '/UID SEARCH UID 50:\*/' => "* SEARCH 50 52 57\r\n{TAG} OK Search completed\r\n",
            '/UID SEARCH SINCE 28-Sep-2026/' => "* SEARCH 41 44\r\n{TAG} OK Search completed\r\n",
        ]);
        $client = new ImapClient($server);
        $client->connect('imap.example.com', 993);

        $this->assertSame([], $client->searchNew(57, now()));
        $this->assertSame([50, 52, 57], $client->searchNew(49, now()));
        $this->assertSame([41, 44], $client->searchNew(null, Carbon::parse('2026-09-28')));
    }

    public function test_it_fetches_a_multipart_quoted_printable_reply_through_literals(): void
    {
        $headers = "From: =?UTF-8?B?QWRhIE9iw60=?= <Ada@Prospect.co>\r\n"
            ."Subject: Re: Quick idea for =?ISO-8859-1?Q?Caf=E9?= Lagos\r\n"
            ."Date: Tue, 29 Sep 2026 10:15:00 +0100\r\n"
            ."Message-ID: <reply-1@prospect.co>\r\n"
            ."In-Reply-To: <camp-abc@sender.com>\r\n"
            ."References: <older@sender.com>\r\n <camp-abc@sender.com>\r\n\r\n";
        $plain = "Hi! Yes, I=E2=80=99m interested =\r\nin a demo.\r\n\r\nOn Mon, Sep 28, 2026 you wrote:\r\n> Original";

        $server = $this->server([
            '/UID FETCH 57 \(UID RFC822\.SIZE BODYSTRUCTURE/' => '* 3 FETCH (UID 57 RFC822.SIZE 2048 BODYSTRUCTURE (("text" "plain" ("charset" "utf-8") NIL NIL "quoted-printable" 120 4 NIL NIL NIL NIL)("text" "html" ("charset" "utf-8") NIL NIL "base64" 300 5 NIL NIL NIL NIL) "alternative" ("boundary" "b1") NIL NIL NIL) BODY[HEADER.FIELDS (FROM TO SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)] {'.strlen($headers)."}\r\n".$headers.")\r\n{TAG} OK Fetch completed\r\n",
            '/UID FETCH 57 \(BODY\.PEEK\[1\]<0\.65536>\)/' => '* 3 FETCH (UID 57 BODY[1]<0> {'.strlen($plain)."}\r\n".$plain.")\r\n{TAG} OK Fetch completed\r\n",
        ]);

        $client = new ImapClient($server);
        $client->connect('imap.example.com', 993);
        $messages = $client->fetchMessages([57]);

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertSame('57', $message->externalId);
        $this->assertSame('ada@prospect.co', $message->fromEmail);
        $this->assertSame('Ada Obí', $message->fromName);
        $this->assertSame('Re: Quick idea for Café Lagos', $message->subject);
        $this->assertSame('<reply-1@prospect.co>', $message->messageId);
        $this->assertSame('<camp-abc@sender.com>', $message->inReplyTo);
        $this->assertSame(['<older@sender.com>', '<camp-abc@sender.com>'], $message->references);
        $this->assertStringStartsWith('Hi! Yes, I’m interested in a demo.', $message->textBody);
        $this->assertSame('2026-09-29 09:15:00', $message->date->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_single_part_base64_latin1_body_is_converted_to_utf8(): void
    {
        $headers = "From: jose@example.es\r\nSubject: Hola\r\nMessage-ID: <x@es>\r\n\r\n";
        $body = base64_encode(mb_convert_encoding('Sí, me interesa. Mañana hablamos.', 'ISO-8859-1', 'UTF-8'));

        $server = $this->server([
            '/UID FETCH 9 \(UID/' => '* 1 FETCH (UID 9 BODYSTRUCTURE ("TEXT" "PLAIN" ("CHARSET" "ISO-8859-1") NIL NIL "BASE64" 60 1 NIL NIL NIL NIL) BODY[HEADER.FIELDS (FROM TO SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)] {'.strlen($headers)."}\r\n".$headers.")\r\n{TAG} OK done\r\n",
            '/UID FETCH 9 \(BODY\.PEEK\[1\]/' => '* 1 FETCH (UID 9 BODY[1]<0> "'.$body."\")\r\n{TAG} OK done\r\n",
        ]);

        $client = new ImapClient($server);
        $client->connect('imap.example.com', 993);
        $message = $client->fetchMessages([9])[0];

        $this->assertSame('Sí, me interesa. Mañana hablamos.', $message->textBody);
        $this->assertNull($message->inReplyTo);
    }

    public function test_an_html_only_reply_is_converted_to_text(): void
    {
        $this->assertSame(['part' => '2', 'subtype' => 'html', 'charset' => 'windows-1252', 'encoding' => '7bit'], BodyStructure::textPart(
            ImapParser::parse('(("image" "png" NIL NIL NIL "base64" 10 NIL NIL NIL NIL)("text" "html" ("charset" "windows-1252") NIL NIL "7bit" 30 2 NIL NIL NIL NIL) "mixed" NIL NIL NIL NIL)')[0]
        ));

        $this->assertSame("Thanks!\nCall me", MimeDecoder::htmlToText('<div>Thanks!</div><p>Call&nbsp;me</p>'));
    }

    public function test_nested_multiparts_number_their_parts_and_skip_attached_messages(): void
    {
        $structure = ImapParser::parse('((("text" "plain" ("charset" "us-ascii") NIL NIL "7bit" 10 1 NIL NIL NIL NIL)("text" "html" NIL NIL NIL "7bit" 20 1 NIL NIL NIL NIL) "alternative" NIL NIL NIL NIL)("application" "pdf" ("name" "a.pdf") NIL NIL "base64" 999 NIL NIL NIL NIL) "mixed" NIL NIL NIL NIL)')[0];

        $this->assertSame('1.1', BodyStructure::textPart($structure)['part']);
    }

    public function test_the_parser_handles_quoted_escapes_nil_and_bracketed_atoms(): void
    {
        $tokens = ImapParser::parse("5 FETCH (UID 7 FLAGS (\\Seen) BODY[HEADER.FIELDS (FROM)] \0".'0'."\0 X \"a \\\"b\\\" c\" Y NIL)", ["From: a@b\r\n"]);

        $this->assertSame(['5', 'FETCH', ['UID', '7', 'FLAGS', ['\\Seen'], 'BODY[HEADER.FIELDS (FROM)]', "From: a@b\r\n", 'X', 'a "b" c', 'Y', null]], $tokens);
    }

    public function test_mime_decoder_walks_a_raw_multipart_message(): void
    {
        $raw = "Content-Type: multipart/alternative; boundary=\"zz\"\r\n\r\n--zz\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>HTML version</p>\r\n--zz\r\nContent-Type: text/plain; charset=iso-8859-1\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nCaf=E9 plain\r\n--zz--\r\n";

        $this->assertSame('Café plain', MimeDecoder::textFromRawMessage($raw));
    }
}
