<?php

namespace App\Console\Commands\Integrations;

use App\Services\Settings\FundingHealthChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:check-funding-health')]
#[Description('Vault, the funding-monitor agent: re-check HeyGen/wallet balances and manual reminder cadences, notifying admins on new low-balance or overdue-reminder crossings')]
class CheckFundingHealth extends Command
{
    public function handle(FundingHealthChecker $checker): void
    {
        $checker->runChecks();

        $this->info('Funding health checks complete.');
    }
}
