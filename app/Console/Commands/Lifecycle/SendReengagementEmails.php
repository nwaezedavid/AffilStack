<?php

namespace App\Console\Commands\Lifecycle;

use App\Services\Lifecycle\LifecycleEmailDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lifecycle:send-reengagement')]
#[Description('Queue the next re-engagement step for customers who have been inactive')]
class SendReengagementEmails extends Command
{
    public function handle(LifecycleEmailDispatcher $dispatcher): void
    {
        $count = $dispatcher->dispatchReengagement();

        $this->info("Queued {$count} re-engagement email(s).");
    }
}
