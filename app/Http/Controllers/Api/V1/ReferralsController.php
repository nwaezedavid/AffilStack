<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReferralEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only referral/earnings summary, via the API (task #6). Payouts
 * keep their own formal request flow (dashboard: /referrals) rather than
 * being requestable through this endpoint — the payout snapshot pattern
 * (see ReferralPayoutService) deliberately only ever runs from an
 * authenticated dashboard session with a real, current payout profile on
 * file, not an automated API call.
 */
class ReferralsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'referral_code' => $user->referralCode(),
            'referral_link' => $user->referral_link,
            'total_referrals' => $user->referrals()->count(),
            'unpaid_approved_commission_cents' => $user->unpaidApprovedCommissionCents(),
            'has_open_payout_request' => $user->hasOpenPayoutRequest(),
            'events_by_status' => ReferralEvent::whereHas('referral', fn ($query) => $query->where('referrer_id', $user->id))
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }
}
