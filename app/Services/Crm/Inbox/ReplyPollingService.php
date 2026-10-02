<?php

namespace App\Services\Crm\Inbox;

use App\Models\EmailConnection;
use Throwable;

/**
 * One scheduler tick of reply detection: walks every mailbox with a live
 * detection channel (Gmail API when enabled, else verified IMAP), least
 * recently polled first, until the time budget is spent — so on shared
 * hosting a tick always ends well inside the ~60s limit, and mailboxes not
 * reached are first in line next tick.
 */
class ReplyPollingService
{
    public function __construct(protected ImapReplyPoller $imap, protected GmailInboxReader $gmail) {}

    /**
     * @return array{mailboxes: int, replies: int, errors: int}
     */
    public function pollDue(?float $deadline = null): array
    {
        $deadline ??= microtime(true) + (int) config('crm.replies.poll_time_budget', 45);
        $stats = ['mailboxes' => 0, 'replies' => 0, 'errors' => 0];

        $connections = EmailConnection::query()
            ->with('user')
            ->where(fn ($q) => $q->where('imap_status', 'success')->orWhere('gmail_read_granted', true))
            ->orderByRaw('coalesce(imap_last_polled_at, gmail_last_polled_at) is not null')
            ->orderByRaw('coalesce(imap_last_polled_at, gmail_last_polled_at)')
            ->limit(200)
            ->get();

        foreach ($connections as $connection) {
            if (microtime(true) >= $deadline - 5) {
                break;
            }

            $result = $this->pollOne($connection);

            if ($result === null) {
                $stats['errors']++;

                continue;
            }

            $stats['mailboxes']++;
            $stats['replies'] += $result['replies'];
        }

        return $stats;
    }

    /**
     * @return array{fetched: int, replies: int}|null null on failure
     */
    public function pollOne(EmailConnection $connection): ?array
    {
        try {
            return match ($connection->replyDetectionMethod()) {
                'gmail' => $this->gmail->poll($connection),
                'imap' => $this->imap->poll($connection),
                default => ['fetched' => 0, 'replies' => 0],
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
