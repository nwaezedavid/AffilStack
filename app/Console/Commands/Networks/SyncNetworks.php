<?php

namespace App\Console\Commands\Networks;

use App\Jobs\SyncNetworkConnection;
use App\Models\NetworkConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('networks:sync')]
#[Description('Queue a sync of every connected affiliate network (programs and commissions)')]
class SyncNetworks extends Command
{
    public function handle(): int
    {
        $count = 0;

        NetworkConnection::query()->active()->each(function (NetworkConnection $connection) use (&$count) {
            SyncNetworkConnection::dispatch($connection);
            $count++;
        });

        $this->info("Queued {$count} network sync(s).");

        return self::SUCCESS;
    }
}
