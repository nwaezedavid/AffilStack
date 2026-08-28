<?php

namespace App\Console\Commands\Signups;

use App\Models\PendingSignup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('signups:prune-expired')]
#[Description('Delete pending signups whose checkout was never completed within the expiry window')]
class PruneExpiredSignups extends Command
{
    public function handle(): void
    {
        $count = PendingSignup::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Pruned {$count} expired pending signup(s).");
    }
}
