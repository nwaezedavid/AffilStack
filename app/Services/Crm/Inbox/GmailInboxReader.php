<?php

namespace App\Services\Crm\Inbox;

use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use App\Services\Crm\GmailOAuthService;
use App\Services\Crm\Imap\InboundEmail;
use App\Services\Crm\Imap\MimeDecoder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reply detection through the Gmail API (gmail.readonly) for users who
 * connected Gmail — incremental via users.history.list from the last
 * historyId, with a users.messages.list bootstrap. Fully built but inert
 * until the admin turns on "Allow Gmail inbox reading" in Google Login
 * settings (needs Google's CASA assessment) AND the user re-consented to
 * the extra scope; until then IMAP covers everyone.
 */
class GmailInboxReader
{
    protected const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function __construct(protected GmailOAuthService $oauth, protected ReplyIngestor $ingestor) {}

    public function isActiveFor(EmailConnection $connection): bool
    {
        return $connection->isGmail()
            && $connection->gmail_read_granted
            && GoogleOauthSetting::current()->gmailReadingAvailable();
    }

    /**
     * @return array{fetched: int, replies: int}
     *
     * @throws RuntimeException
     */
    public function poll(EmailConnection $connection): array
    {
        if (! $this->isActiveFor($connection)) {
            return ['fetched' => 0, 'replies' => 0];
        }

        $token = $this->oauth->accessTokenFor($connection);
        $max = (int) config('crm.replies.max_messages_per_mailbox', 50);
        [$messageIds, $historyId] = $connection->gmail_history_id
            ? $this->changedSince($token, (string) $connection->gmail_history_id, $max)
            : [null, null];

        if ($messageIds === null) {
            [$messageIds, $historyId] = $this->bootstrap($token, $max);
        }

        $fetched = 0;
        $replies = 0;

        foreach (array_slice(array_unique($messageIds), 0, $max) as $id) {
            $email = $this->fetch($token, $id);

            if (! $email) {
                continue;
            }

            $fetched++;

            if ($this->ingestor->ingest($connection->user, $email, 'gmail')) {
                $replies++;
            }
        }

        $connection->update([
            'gmail_history_id' => $historyId ?? $connection->gmail_history_id,
            'gmail_last_polled_at' => now(),
        ]);

        return ['fetched' => $fetched, 'replies' => $replies];
    }

    /**
     * @return array{0: array<int, string>|null, 1: string|null} null ids when the history window expired
     */
    protected function changedSince(string $token, string $historyId, int $max): array
    {
        $response = Http::withToken($token)->timeout(20)->get(self::API.'/history', [
            'startHistoryId' => $historyId,
            'historyTypes' => 'messageAdded',
            'labelId' => 'INBOX',
            'maxResults' => $max,
        ]);

        if ($response->status() === 404) {
            return [null, null];
        }

        if ($response->failed()) {
            throw new RuntimeException('Gmail history request failed: '.$response->status());
        }

        $ids = [];

        foreach ($response->json('history', []) as $entry) {
            foreach ($entry['messagesAdded'] ?? [] as $added) {
                if (! empty($added['message']['id'])) {
                    $ids[] = (string) $added['message']['id'];
                }
            }
        }

        return [$ids, (string) ($response->json('historyId') ?: $historyId)];
    }

    /**
     * @return array{0: array<int, string>, 1: string|null}
     */
    protected function bootstrap(string $token, int $max): array
    {
        $profile = Http::withToken($token)->timeout(20)->get(self::API.'/profile');

        if ($profile->failed()) {
            throw new RuntimeException('Gmail profile request failed: '.$profile->status());
        }

        $list = Http::withToken($token)->timeout(20)->get(self::API.'/messages', [
            'q' => 'in:inbox newer_than:'.max(1, (int) config('crm.replies.initial_lookback_days', 3)).'d',
            'maxResults' => $max,
        ]);

        if ($list->failed()) {
            throw new RuntimeException('Gmail messages request failed: '.$list->status());
        }

        return [
            collect($list->json('messages', []))->pluck('id')->filter()->map(fn ($id) => (string) $id)->all(),
            $profile->json('historyId') ? (string) $profile->json('historyId') : null,
        ];
    }

    protected function fetch(string $token, string $id): ?InboundEmail
    {
        $response = Http::withToken($token)->timeout(20)->get(self::API.'/messages/'.rawurlencode($id), ['format' => 'full']);

        if ($response->failed()) {
            return null;
        }

        $payload = $response->json('payload', []);
        $headers = $this->headers($payload['headers'] ?? []);
        $text = $this->textOf($payload, 'plain') ?? $this->textOf($payload, 'html') ?? (string) $response->json('snippet', '');

        return InboundEmail::fromHeaders($id, $headers, $text, $response->json('threadId'));
    }

    /**
     * @param  array<int, array{name?: string, value?: string}>  $list
     * @return array<string, string>
     */
    protected function headers(array $list): array
    {
        $headers = [];

        foreach ($list as $header) {
            $name = strtolower((string) ($header['name'] ?? ''));

            if ($name !== '' && ! isset($headers[$name])) {
                $headers[$name] = MimeDecoder::decodeHeader((string) ($header['value'] ?? ''));
            }
        }

        return $headers;
    }

    /**
     * Depth-first search for a text/{plain|html} part with inline data.
     *
     * @param  array<string, mixed>  $part
     */
    protected function textOf(array $part, string $subtype): ?string
    {
        if (($part['mimeType'] ?? '') === 'text/'.$subtype && ! empty($part['body']['data'])) {
            $bytes = base64_decode(strtr((string) $part['body']['data'], '-_', '+/'), false);
            $charset = MimeDecoder::contentType($this->headers($part['headers'] ?? [])['content-type'] ?? 'text/plain; charset=utf-8')['charset'] ?? 'UTF-8';
            $text = MimeDecoder::toUtf8((string) $bytes, $charset);

            return $subtype === 'html' ? MimeDecoder::htmlToText($text) : trim($text);
        }

        foreach ($part['parts'] ?? [] as $child) {
            if (is_array($child) && ($found = $this->textOf($child, $subtype)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
