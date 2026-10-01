<?php

namespace App\Console\Commands\Crm;

use App\Services\Crm\Inbox\ReplyPollingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reply detection, every five minutes: checks connected mailboxes (IMAP,
 * or the Gmail API once enabled) for replies from CRM contacts — see
 * ReplyPollingService for the time budget that keeps a tick under ~50s.
 */
#[Signature('crm:poll-replies')]
#[Description('Check connected mailboxes for replies from CRM contacts')]
class PollReplies extends Command
{
    public function handle(ReplyPollingService $service): void
    {
        $stats = $service->pollDue();

        $this->info("Checked {$stats['mailboxes']} mailbox(es): {$stats['replies']} new repl(ies), {$stats['errors']} error(s).");
    }
}
