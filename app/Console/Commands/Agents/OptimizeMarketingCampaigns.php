<?php

namespace App\Console\Commands\Agents;

use App\Services\Agents\BrainAgentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agents:marketing-optimize-campaigns')]
#[Description('Brain (the Marketing Agent) reviews and adjusts every live campaign via the connected Meta Ads MCP server')]
class OptimizeMarketingCampaigns extends Command
{
    public function handle(BrainAgentService $brain): void
    {
        $processed = $brain->optimizeRunningCampaigns();

        $this->info("Brain: {$processed} live campaign(s) reviewed.");
    }
}
