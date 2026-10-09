<?php

namespace App\Console\Commands\Signups;

use App\Models\PendingSignup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('signups:prune-expired')]
#[Description('Delete pending signups whose checkout was never completed within the expiry window')]
class PruneExpiredSignups extends Command
{
    public function handle(): void
    {
        // A week of grace past expiry for late settlements (bank transfer,
        // USSD), and never a signup whose payment went through or is being
        // processed — deleting it would leave a paid customer with no account.
        $count = PendingSignup::where('status', 'pending')
            ->where('expires_at', '<', now()->subDays(7))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payment_transactions')
                    ->whereColumn('payment_transactions.pending_signup_id', 'pending_signups.id')
                    ->whereIn('payment_transactions.status', ['successful', 'processing', 'needs_review']);
            })
            ->delete();

        $this->info("Pruned {$count} expired pending signup(s).");
    }
}
