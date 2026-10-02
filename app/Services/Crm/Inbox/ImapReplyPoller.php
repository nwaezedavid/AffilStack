<?php

namespace App\Services\Crm\Inbox;

use App\Models\EmailConnection;
use App\Services\Crm\ConnectionErrorExplainer;
use App\Services\Crm\Imap\ImapClient;
use App\Services\Crm\Imap\ImapException;
use App\Services\Crm\Imap\ImapTransport;

/**
 * Reads new messages from one user's IMAP inbox since the last UID seen
 * (or the last few days on first run, or after the server resets
 * UIDVALIDITY) and hands each to ReplyIngestor. Capped per run so a big
 * mailbox catches up over several scheduler ticks instead of blowing the
 * shared-hosting time limit.
 */
class ImapReplyPoller
{
    public function __construct(protected ReplyIngestor $ingestor, protected ConnectionErrorExplainer $explainer) {}

    /**
     * @return array{fetched: int, replies: int}
     *
     * @throws ImapException
     */
    public function poll(EmailConnection $connection): array
    {
        $client = new ImapClient(app(ImapTransport::class));
        $max = (int) config('crm.replies.max_messages_per_mailbox', 50);
        $fetched = 0;
        $replies = 0;

        try {
            $client->connect(
                (string) $connection->imapCredential('host'),
                (int) $connection->imapCredential('port', 993),
                (string) $connection->imapCredential('encryption', 'ssl'),
                (int) config('crm.replies.imap_timeout', 15),
            );
            $client->login((string) $connection->imapCredential('username'), (string) $connection->imapCredential('password'));
            $mailbox = $client->select('INBOX');

            $lastUid = $connection->imap_last_uid;

            if ($mailbox['uidvalidity'] !== null && $connection->imap_uidvalidity !== null && (int) $connection->imap_uidvalidity !== $mailbox['uidvalidity']) {
                $lastUid = null;
            }

            $since = ($connection->imap_last_polled_at ?? now())->copy()->subDays((int) config('crm.replies.initial_lookback_days', 3));
            $uids = array_slice($client->searchNew($lastUid, $since), 0, $max);

            foreach (array_chunk($uids, 10) as $chunk) {
                foreach ($client->fetchMessages($chunk, (int) config('crm.replies.max_body_bytes', 65536)) as $email) {
                    $fetched++;

                    if ($this->ingestor->ingest($connection->user, $email, 'imap')) {
                        $replies++;
                    }
                }
            }

            $connection->update([
                'imap_uidvalidity' => $mailbox['uidvalidity'],
                'imap_last_uid' => $uids !== [] ? max($uids) : ($lastUid ?? (($mailbox['uidnext'] ?? 1) - 1)),
                'imap_last_polled_at' => now(),
                'imap_status' => 'success',
                'imap_message' => 'Watching your inbox for replies.',
            ]);

            $client->logout();
        } catch (ImapException $e) {
            $connection->update([
                'imap_last_polled_at' => now(),
                'imap_status' => $e->kind === 'auth' ? 'failed' : $connection->imap_status,
                'imap_message' => $e->kind === 'auth' ? $this->explainer->explain($e->getMessage(), 'imap', (string) $connection->imapCredential('host')) : $connection->imap_message,
            ]);

            throw $e;
        }

        return ['fetched' => $fetched, 'replies' => $replies];
    }
}
