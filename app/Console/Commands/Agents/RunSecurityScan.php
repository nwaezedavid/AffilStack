<?php

namespace App\Console\Commands\Agents;

use App\Services\Agents\SecurityScanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agents:security-scan')]
#[Description("Run Tom's (the Security Agent's) daily scan for known vulnerabilities and misconfigurations")]
class RunSecurityScan extends Command
{
    public function handle(SecurityScanService $scanner): void
    {
        $findings = $scanner->scan();

        $this->info("Security scan complete: {$findings->count()} finding(s) open or updated.");
    }
}
