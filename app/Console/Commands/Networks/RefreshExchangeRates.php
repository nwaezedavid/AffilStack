<?php

namespace App\Console\Commands\Networks;

use App\Models\Earning;
use App\Support\ExchangeRates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fx:refresh')]
#[Description('Fetch today\'s exchange rates and fill in US-dollar values for earnings missing one')]
class RefreshExchangeRates extends Command
{
    public function handle(): int
    {
        if (ExchangeRates::refresh()) {
            $this->info('Exchange rates updated ('.ExchangeRates::updatedOn().').');
        } else {
            $this->warn('Couldn\'t fetch exchange rates; keeping the last ones.');
        }

        $filled = 0;

        Earning::query()->whereNull('usd_cents')->chunkById(500, function ($earnings) use (&$filled) {
            foreach ($earnings as $earning) {
                $usd = ExchangeRates::toUsdCents((int) $earning->amount_cents, (string) $earning->currency);

                if ($usd !== null) {
                    $earning->forceFill(['usd_cents' => $usd])->saveQuietly();
                    $filled++;
                }
            }
        });

        $this->info("Filled in {$filled} earning(s).");

        return self::SUCCESS;
    }
}
