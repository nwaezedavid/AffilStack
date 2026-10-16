<?php

namespace App\Console\Commands\Marketplace;

use App\Services\Marketplace\AddonBilling;
use Illuminate\Console\Command;

/**
 * Charges paid add-ons due to renew, and ends cancelled or unpaid ones.
 */
class RenewAddonSubscriptions extends Command
{
    protected $signature = 'marketplace:renew';

    protected $description = 'Renew paid add-on subscriptions and end cancelled or unpaid ones';

    public function handle(AddonBilling $billing): int
    {
        $stats = $billing->renewDue();

        $this->info("Renewed {$stats['renewed']}, failed {$stats['failed']}, ended {$stats['ended']}.");

        return self::SUCCESS;
    }
}
