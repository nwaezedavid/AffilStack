<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\MobileAccountResource;
use App\Models\CreditLedger;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Services\CustomerWallet\CustomerWalletManager;
use App\Support\Mobile\SeatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * More > Plan & credits in the phone app: view only. The plan, what it
 * includes each month, the credit and wallet balances and recent activity
 * on both. Nothing here sells anything or links to buying: plans, credits
 * and top-ups are bought on the website (App Store guideline 3.1.1 / Google
 * Play's Payments policy — the app is "consumption-only"). Team seats see
 * balances but not the owner's history, as on the website, where billing
 * is owner-only.
 */
class BillingController extends Controller
{
    /**
     * Readable names for the credit ledger's reasons. Anything not listed
     * is turned into words ("crm_campaign" → "Crm campaign").
     *
     * @var array<string, string>
     */
    protected const REASONS = [
        'research' => 'Product research',
        'blog_article' => 'Blog article',
        'linkedin_post' => 'LinkedIn post',
        'linkedin_article' => 'LinkedIn article',
        'linkedin_keywords' => 'LinkedIn keyword research',
        'linkedin_dm_sequence' => 'LinkedIn message sequence',
        'x_thread' => 'X thread',
        'youtube_script' => 'Video script',
        'ugc_video' => 'One-click video',
        'lead_found' => 'New lead found',
        'crm_campaign' => 'Email campaign',
        'crm_personal_opening' => 'Personal email opening',
        'crm_reply_draft' => 'Reply drafted with AI',
        'linkedin_reply_draft' => 'LinkedIn reply drafted with AI',
        'extension_reply_suggestion' => 'Reply suggestion (extension)',
        'extension_linkedin_message' => 'LinkedIn message (extension)',
        'intelligence_centre' => 'Intelligence report',
        'monthly_grant' => 'Monthly plan credits',
        'renewal_grant' => 'Monthly plan credits',
        'plan_change_grant' => 'Credits for your new plan',
        'trial_grant' => 'Trial credits',
        'admin_assigned_grant' => 'Plan credits',
        'admin_adjustment' => 'Adjustment by AffilStack',
        'birthday_gift' => 'Birthday gift',
        'refund_clawback' => 'Refund',
    ];

    public function show(Request $request, CustomerWalletManager $wallets): JsonResponse
    {
        $user = $request->user();
        $owner = $user->billableUser();
        $wallet = $wallets->walletFor($user);
        $showsHistory = SeatAccess::allows($user, 'billing.index');

        return response()->json([
            'data' => [
                'account' => new MobileAccountResource($user),
                'credits' => [
                    'balance' => (int) $owner->credits_balance,
                    'monthly_allowance' => $owner->activeSubscription?->plan?->credits_per_month !== null
                        ? (int) $owner->activeSubscription->plan->credits_per_month
                        : null,
                ],
                'wallet' => [
                    'balance_minor' => (int) $wallet->balance_minor,
                    'currency' => $wallet->currency,
                    'formatted' => $wallet->formattedBalance(),
                ],
                'shows_history' => $showsHistory,
                'credit_activity' => $showsHistory
                    ? $owner->creditLedger()->latest('id')->limit(30)->get()->map(fn (CreditLedger $entry): array => [
                        'id' => $entry->id,
                        'label' => self::REASONS[$entry->reason] ?? Str::ucfirst(str_replace(['_', '.', ':'], ' ', (string) $entry->reason)),
                        'amount' => (int) $entry->amount,
                        'balance_after' => (int) $entry->balance_after,
                        'created_at' => $entry->created_at?->toIso8601String(),
                    ])->values()
                    : [],
                'wallet_activity' => $showsHistory
                    ? CustomerWalletTransaction::query()->where('customer_wallet_id', $wallet->id)->latest('id')->limit(20)->get()->map(fn (CustomerWalletTransaction $transaction): array => [
                        'id' => $transaction->id,
                        'label' => CustomerWalletTransaction::TYPE_LABELS[$transaction->type] ?? Str::ucfirst(str_replace('_', ' ', (string) $transaction->type)),
                        'amount_minor' => (int) $transaction->amount_minor,
                        'formatted' => CustomerWallet::format((int) $transaction->amount_minor, (string) $transaction->currency),
                        'created_at' => $transaction->created_at?->toIso8601String(),
                    ])->values()
                    : [],
            ],
        ]);
    }
}
