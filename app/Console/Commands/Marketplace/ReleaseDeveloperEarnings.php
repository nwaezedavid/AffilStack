<?php

namespace App\Console\Commands\Marketplace;

use App\Services\Marketplace\DeveloperPayouts;
use Illuminate\Console\Command;

/**
 * Makes developer earnings past the refund window available for payout.
 */
class ReleaseDeveloperEarnings extends Command
{
    protected $signature = 'marketplace:release-earnings';

    protected $description = 'Make developer earnings past the refund window available for payout';

    public function handle(DeveloperPayouts $payouts): int
    {
        $this->info('Released '.$payouts->release().' earnings.');

        return self::SUCCESS;
    }
}
