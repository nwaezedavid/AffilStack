<?php

namespace App\Console\Commands\Lifecycle;

use App\Services\Lifecycle\LifecycleEmailDispatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lifecycle:send-festive')]
#[Description('Queue festive-season greetings (by country and religion) for users whose local time has reached the send hour')]
class SendFestiveEmails extends Command
{
    public function handle(LifecycleEmailDispatcher $dispatcher): void
    {
        $count = $dispatcher->dispatchFestive();

        $this->info("Queued {$count} festive email(s).");
    }
}
