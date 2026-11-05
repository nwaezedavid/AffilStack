<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileAccountResource;
use App\Models\AgentActivity;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Services\Dashboard\ProgressCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/mobile/home — everything the app's Home screen shows in one
 * call: the account and plan, credits, the customer wallet, the
 * dashboard's "What AffilStack has done for you" counter and how many
 * AI-agent suggestions are waiting for approval.
 */
class HomeController extends Controller
{
    public function show(Request $request, ProgressCounter $progress, CustomerWalletManager $wallets): JsonResponse
    {
        $user = $request->user();
        $wallet = $wallets->walletFor($user);

        return response()->json([
            'account' => new MobileAccountResource($user),
            'wallet' => [
                'balance_minor' => (int) $wallet->balance_minor,
                'currency' => $wallet->currency,
                'formatted' => $wallet->formattedBalance(),
            ],
            'progress' => $progress->for($user),
            'agents' => [
                'pending_approvals' => AgentActivity::query()->where('user_id', $user->id)->where('status', AgentActivity::STATUS_NEEDS_APPROVAL)->count(),
            ],
        ]);
    }
}
