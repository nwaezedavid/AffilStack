<?php

namespace App\Console\Commands\Agents;

use App\Services\Agents\Customer\ReplyCopilot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agents:reply-copilot')]
#[Description('Reply Copilot: check inboxes, draft answers to new replies, send due autopilot replies')]
class RunReplyCopilot extends Command
{
    public function handle(ReplyCopilot $copilot): void
    {
        $result = $copilot->tick();

        $this->info("Polled {$result['polled']} inbox(es), drafted {$result['drafted']}, sent {$result['sent']}.");
    }
}
