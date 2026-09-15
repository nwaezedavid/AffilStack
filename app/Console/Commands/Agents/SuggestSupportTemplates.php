<?php

namespace App\Console\Commands\Agents;

use App\Services\Agents\SamAgentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agents:support-suggest-templates')]
#[Description('Sam (the Support Agent) drafts new canned reply templates from recent resolved tickets')]
class SuggestSupportTemplates extends Command
{
    public function handle(SamAgentService $sam): void
    {
        $created = $sam->suggestTemplates();

        $this->info("Sam: {$created} new canned reply suggestion(s) drafted for review.");
    }
}
