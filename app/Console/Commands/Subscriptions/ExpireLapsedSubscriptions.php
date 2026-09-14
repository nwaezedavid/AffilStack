<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Payments\SubscriptionRenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire-lapsed')]
#[Description('Expire subscriptions whose current period ended without being renewed, revoking access')]
class ExpireLapsedSubscriptions extends Command
{
    public function handle(SubscriptionRenewalService $renewals): void
    {
        $count = $renewals->expireLapsed();

        $this->info("Expired {$count} lapsed subscription(s).");
    }
}
