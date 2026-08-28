<?php

namespace App\Services\Credits;

use App\Models\CreditLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single choke point for every credit movement in the platform. Every AI
 * generation module must spend through here (never touch
 * User::credits_balance directly) so the ledger is always the source of
 * truth an admin can audit against the running balance.
 *
 * Every method resolves $user to its billableUser() first — itself
 * normally, or its agency owner for a team seat (item 10) — so a seat's
 * generations are billed to (and its balance checks run against) whoever
 * actually pays for the account, without any generation service needing to
 * know seats exist.
 */
class CreditManager
{
    public function balance(User $user): int
    {
        return $user->billableUser()->fresh()->credits_balance;
    }

    public function hasEnough(User $user, int $amount): bool
    {
        return $this->balance($user) >= $amount;
    }

    public function spend(User $user, int $amount, string $reason, ?Model $reference = null): CreditLedger
    {
        $user = $user->billableUser();

        return DB::transaction(function () use ($user, $amount, $reason, $reference) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->credits_balance < $amount) {
                throw new InsufficientCreditsException(
                    "User {$user->id} has {$locked->credits_balance} credits, needs {$amount} for '{$reason}'."
                );
            }

            $locked->decrement('credits_balance', $amount);

            return CreditLedger::create([
                'user_id' => $user->id,
                'amount' => -$amount,
                'balance_after' => $locked->credits_balance,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }

    public function grant(User $user, int $amount, string $reason, ?Model $reference = null): CreditLedger
    {
        $user = $user->billableUser();

        return DB::transaction(function () use ($user, $amount, $reason, $reference) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $locked->increment('credits_balance', $amount);

            return CreditLedger::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $locked->credits_balance,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }
}
