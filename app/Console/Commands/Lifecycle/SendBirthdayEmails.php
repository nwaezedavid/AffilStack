<?php

namespace App\Console\Commands\Lifecycle;

use App\Services\Lifecycle\LifecycleEmailDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lifecycle:send-birthday')]
#[Description('Queue happy-birthday emails for users whose local time has reached the send hour on their birthday')]
class SendBirthdayEmails extends Command
{
    public function handle(LifecycleEmailDispatcher $dispatcher): void
    {
        $count = $dispatcher->dispatchBirthdays();

        $this->info("Queued {$count} birthday email(s).");
    }
}
