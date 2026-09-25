<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The general-purpose API's account-identity endpoint (task #6, general
 * API + team automation). Reports through billableUser()/onSharedTeamPlan()
 * the same way the dashboard does, so a team seat's own token sees its own
 * true standing (whose plan it's billed against, whether it's a shared
 * Business-tier seat) rather than a blank/owner-only view.
 */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_seat' => $user->isSeat(),
            'seat_role' => $user->seat_role,
            'on_shared_team_plan' => $user->onSharedTeamPlan(),
            'credits_balance' => $user->billableUser()->credits_balance,
            'plan' => $user->billableUser()->activeSubscription?->plan?->name,
            // The API usage prepay wallet (separate real-money balance from
            // credits_balance above) — see ApiWalletManager. Also resolved
            // through billableUser() so a team seat's own token reports the
            // balance its metered calls actually draw from.
            'api_wallet_balance_cents' => $user->billableUser()->api_wallet_balance_cents,
        ]);
    }
}
