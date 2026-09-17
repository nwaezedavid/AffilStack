<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Audit gap #4: the other half of self-service account deletion's 30-day
 * grace period — ProfileController::destroy() soft-deletes immediately;
 * this permanently erases anyone still soft-deleted 30 days later, unless
 * an admin restored them first from the Users resource in the meantime.
 */
#[Signature('users:purge-deleted')]
#[Description('Permanently delete user accounts whose 30-day self-deletion grace period has passed')]
class PurgeDeletedAccounts extends Command
{
    public function handle(): void
    {
        $users = User::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(30))
            ->get();

        foreach ($users as $user) {
            $user->forceDelete();
        }

        $this->info("Permanently deleted {$users->count()} account(s) past their 30-day grace period.");
    }
}
