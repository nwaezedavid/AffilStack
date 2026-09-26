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
     * unattached, admin-approved commission event in the given currency.
     * A mixed-currency affiliate can hold one open request per currency —
     * request USD today, EUR tomorrow — rather than being limited to
     * whichever currency happens to have the largest balance (audit gap
     * #5). When no currency is given (kept for backward compatibility
     * with callers that predate multi-currency support), falls back to
     * whichever currency currently has the largest unattached balance.
     */
    public function requestPayout(User $user, ?string $currency = null): ReferralPayout
    {
        if (! $user->hasPayoutMethodOnFile()) {
            throw new InvalidArgumentException('Add your payout details before requesting a payout.');
        }

        // Everything below runs under a lock on the affiliate's own row, so
        // two simultaneous "Request payout" clicks can't both pass the
        // open-request check and both claim the same commissions.
        return DB::transaction(function () use ($user, $currency) {
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($user->hasOpenPayoutRequest($currency)) {
                throw new RuntimeException($currency
                    ? "You already have a {$currency} payout request being processed."
                    : 'You already have a payout request being processed.');
            }

            $claimable = ReferralEvent::whereHas('referral', fn ($query) => $query->where('referrer_id', $user->id))
                ->where('status', 'approved')
                ->whereNull('referral_payout_id')
                ->lockForUpdate()
                ->get();

            if ($claimable->isEmpty()) {
                throw new InvalidArgumentException('You have no approved commissions to pay out yet.');
            }

            $byCurrency = $claimable->groupBy('currency');
            $currency ??= $byCurrency->map->sum('amount_cents')->sortDesc()->keys()->first();

            $events = $byCurrency->get($currency, collect());
            $total = $events->sum('amount_cents');

            if ($total < (int) config('referrals.minimum_payout_cents')) {
                $minimum = number_format(config('referrals.minimum_payout_cents') / 100, 2);
                throw new InvalidArgumentException("You need at least {$minimum} {$currency} in approved commissions to request a payout.");
            }

            $payout = ReferralPayout::create([
                'user_id' => $user->id,
                'amount_cents' => $total,
                'currency' => $currency,
                'status' => 'requested',
                'payout_method' => $user->payout_method,
                'payout_details' => $user->payout_details,
                'requested_at' => now(),
            ]);

            $claimed = ReferralEvent::whereIn('id', $events->pluck('id'))
                ->whereNull('referral_payout_id')
                ->update(['referral_payout_id' => $payout->id]);

            if ($claimed !== $events->count()) {
                throw new RuntimeException('Your commissions changed while this request was being made — please try again.');
            }

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
        DB::transaction(function () use ($payout, $admin, $reference, $note) {
            // Conditional transition — two admins (or a replayed request)
            // can't both mark it paid, and a concurrent reject can't
            // release commissions that are simultaneously being paid.
            $updated = ReferralPayout::whereKey($payout->id)
                ->whereIn('status', ['requested', 'disbursing'])
                ->update([
                    'status' => 'paid',
                    'reference' => $reference,
                    'note' => $note,
                    'processed_at' => now(),
                    'processed_by_id' => $admin->id,
                ]);

            if ($updated !== 1) {
                throw new InvalidArgumentException('Only a requested payout can be marked paid.');
            }

            $payout->events()->update(['status' => 'paid']);
        });

        $payout->refresh();

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
        DB::transaction(function () use ($payout, $admin, $note) {
            $updated = ReferralPayout::whereKey($payout->id)
                ->where('status', 'requested')
                ->update([
                    'status' => 'rejected',
                    'note' => $note,
                    'processed_at' => now(),
                    'processed_by_id' => $admin->id,
                ]);

            if ($updated !== 1) {
                throw new InvalidArgumentException('Only a requested payout can be rejected.');
            }

            $payout->events()->update(['referral_payout_id' => null]);
        });

        $payout->refresh();

        $payout->user->notify(new ReferralPayoutRejected($payout));
    }
}
