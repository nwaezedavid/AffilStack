<?php

namespace App\Console\Commands\Trials;

use App\Services\Trials\TrialInviteService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trials:process')]
#[Description('Invite-only free trials: send ending reminders, charge ended trials and expire unused invites')]
class ProcessTrials extends Command
{
    public function handle(TrialInviteService $trials): void
    {
        $stats = $trials->process();

        $this->info("Reminded {$stats['reminded']}, converted {$stats['converted']}, failed {$stats['failed']}, expired {$stats['expired']} invite(s).");
    }
}
