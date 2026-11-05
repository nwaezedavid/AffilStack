<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Payments\SubscriptionRenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:charge-renewals')]
#[Description('Renew subscriptions AffilStack renews itself: customer wallet first, then the saved card')]
class ChargeDueRenewals extends Command
{
    public function handle(SubscriptionRenewalService $renewals): void
    {
        $stats = $renewals->chargeDueRenewals();

        $this->info("Renewed {$stats['renewed']} subscription(s); {$stats['failed']} charge(s) failed.");
    }
}
