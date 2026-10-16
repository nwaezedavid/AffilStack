<?php

namespace App\Services\Marketplace;

use App\Models\DeveloperEarning;
use App\Models\DeveloperProfile;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Support\ReferralProgram;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * A developer's earnings and payout requests. Payouts are ReferralPayout
 * rows (source "developer"), so an admin pays them from the same screen
 * and with the same methods as partner payouts.
 */
class DeveloperPayouts
{
    /**
     * Makes earnings past the refund window available.
     */
    public function release(): int
    {
        return DeveloperEarning::query()
            ->where('status', 'pending')
            ->where('available_at', '<=', now())
            ->update(['status' => 'approved']);
    }

    /**
     * @return array{available: int, pending: int, paid: int, currency: string}
     */
    public function balance(DeveloperProfile $developer): array
    {
        $earnings = $developer->earnings()->where('currency', 'USD');

        return [
            'available' => (int) (clone $earnings)->where('status', 'approved')->whereNull('referral_payout_id')->sum('amount_cents'),
            'pending' => (int) (clone $earnings)->where('status', 'pending')->sum('amount_cents'),
            'paid' => (int) (clone $earnings)->where('status', 'paid')->sum('amount_cents'),
            'currency' => 'USD',
        ];
    }

    public function requestPayout(User $user): ReferralPayout
    {
        $developer = $user->developerProfile;

        if (! $developer) {
            throw new InvalidArgumentException('Set up your developer profile first.');
        }

        if (! $user->hasPayoutMethodOnFile()) {
            throw new InvalidArgumentException('Add your payout details before requesting a payout.');
        }

        $this->release();

        return DB::transaction(function () use ($user, $developer) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($user->hasOpenPayoutRequest('USD', 'developer')) {
                throw new RuntimeException('You already have a payout request being processed.');
            }

            $earnings = $developer->earnings()
                ->where('currency', 'USD')
                ->where('status', 'approved')
                ->whereNull('referral_payout_id')
                ->lockForUpdate()
                ->get();

            $total = (int) $earnings->sum('amount_cents');
            $minimum = ReferralProgram::minimumPayoutCents('USD');

            if ($total <= 0 || $total < $minimum) {
                throw new InvalidArgumentException('You need at least '.ReferralProgram::money($minimum, 'USD').' in available earnings to request a payout.');
            }

            $payout = ReferralPayout::create([
                'user_id' => $user->id,
                'source' => 'developer',
                'amount_cents' => $total,
                'currency' => 'USD',
                'status' => 'requested',
                'payout_method' => $user->payout_method,
                'payout_details' => $user->payout_details,
                'requested_at' => now(),
            ]);

            $claimed = DeveloperEarning::whereIn('id', $earnings->pluck('id'))->whereNull('referral_payout_id')->update(['referral_payout_id' => $payout->id]);

            if ($claimed !== $earnings->count()) {
                throw new RuntimeException('Your earnings changed while this request was being made — please try again.');
            }

            return $payout;
        });
    }
}
