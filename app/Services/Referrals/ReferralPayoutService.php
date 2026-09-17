<?php

namespace App\Services\Referrals;

use App\Models\ReferralEvent;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Notifications\ReferralPayoutProcessed;
use App\Notifications\ReferralPayoutRejected;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The referral program overhaul's real payout workflow — replaces hand-
 * flipping a ReferralEvent's status to "paid" with an actual request/
 * process/reject cycle that snapshots where the money goes and leaves a
 * reference behind. See ReferralPayout and User::unpaidApprovedCommissionCents().
 */
class ReferralPayoutService
{
    /**
     * Called from the affiliate's own dashboard. Claims every currently
     * unattached, admin-approved commission event in the user's dominant
     * currency (there's no multi-currency payout in v1 — a mixed-currency
     * affiliate just gets whichever currency has the larger unattached
     * balance this time, and the rest waits for their next request).
     */
    public function requestPayout(User $user): ReferralPayout
    {
        if (! $user->hasPayoutMethodOnFile()) {
            throw new InvalidArgumentException('Add your payout details before requesting a payout.');
        }

        if ($user->hasOpenPayoutRequest()) {
            throw new RuntimeException('You already have a payout request being processed.');
        }

        $claimable = ReferralEvent::whereHas('referral', fn ($query) => $query->where('referrer_id', $user->id))
            ->where('status', 'approved')
            ->whereNull('referral_payout_id')
            ->get();

        if ($claimable->isEmpty()) {
            throw new InvalidArgumentException('You have no approved commissions to pay out yet.');
        }

        $currency = $claimable->groupBy('currency')->map->sum('amount_cents')->sortDesc()->keys()->first();
        $events = $claimable->where('currency', $currency);
        $total = $events->sum('amount_cents');

        if ($total < (int) config('referrals.minimum_payout_cents')) {
            $minimum = number_format(config('referrals.minimum_payout_cents') / 100, 2);
            throw new InvalidArgumentException("You need at least \${$minimum} in approved commissions to request a payout.");
        }

        return DB::transaction(function () use ($user, $events, $total, $currency) {
            $payout = ReferralPayout::create([
                'user_id' => $user->id,
                'amount_cents' => $total,
                'currency' => $currency,
                'status' => 'requested',
                'payout_method' => $user->payout_method,
                'payout_details' => $user->payout_details,
                'requested_at' => now(),
            ]);

            ReferralEvent::whereIn('id', $events->pluck('id'))->update(['referral_payout_id' => $payout->id]);

            return $payout;
        });
    }

    /**
     * Admin marks a payout sent, with a reference to prove it (a PayPal
     * transaction id, a bank reference, whatever the method calls for).
     * Cascades every attached event to "paid" — that cascade is the ONLY
     * way an event ever reaches "paid".
     */
    public function processPayout(ReferralPayout $payout, User $admin, string $reference, ?string $note = null): void
    {
        if (! $payout->isRequested()) {
            throw new InvalidArgumentException('Only a requested payout can be marked paid.');
        }

        DB::transaction(function () use ($payout, $admin, $reference, $note) {
            $payout->update([
                'status' => 'paid',
                'reference' => $reference,
                'note' => $note,
                'processed_at' => now(),
                'processed_by_id' => $admin->id,
            ]);

            $payout->events()->update(['status' => 'paid']);
        });

        $payout->user->notify(new ReferralPayoutProcessed($payout));
    }

    /**
     * Admin can't fulfill the request (bad payout details, a commission
     * turned out to be fraudulent, etc.) — releases the attached events
     * back to unattached "approved" so they're claimable by a future
     * request rather than stuck forever on a dead payout.
     */
    public function rejectPayout(ReferralPayout $payout, User $admin, string $note): void
    {
        if (! $payout->isRequested()) {
            throw new InvalidArgumentException('Only a requested payout can be rejected.');
        }

        DB::transaction(function () use ($payout, $admin, $note) {
            $payout->events()->update(['referral_payout_id' => null]);

            $payout->update([
                'status' => 'rejected',
                'note' => $note,
                'processed_at' => now(),
                'processed_by_id' => $admin->id,
            ]);
        });

        $payout->user->notify(new ReferralPayoutRejected($payout));
    }
}
