<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\ReferralPayout;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin API (task #6: "Add API features in the admin backend so I can
 * ... connect to third-party application"). Gated by EnsureAdminApiToken —
 * every route here requires a token minted from Filament: System > API
 * Tokens for a full admin, entirely separate from the user-scoped /v1/*
 * endpoints. One controller, several read-only reports, following the
 * same single-controller-per-domain shape as ExtensionApiController —
 * this is deliberately reporting/oversight only for its first version, not
 * a way to mutate accounts, plans, or money from outside the admin panel.
 */
class AdminApiController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->whereNull('agency_owner_id')
            ->with('activeSubscription.plan:id,name')
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($users);
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::orderBy('sort_order')->get());
    }

    public function referralPayouts(Request $request): JsonResponse
    {
        $payouts = ReferralPayout::with('user:id,name,email')
            ->latest('requested_at')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return response()->json($payouts);
    }

    /**
     * The same MRR/monthly-revenue calculation the admin dashboard's
     * RevenueOverview widget shows — see app/Filament/Widgets/RevenueOverview.php.
     */
    public function revenue(): JsonResponse
    {
        $mrrCents = Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw("SUM(CASE WHEN billing_cycle = 'yearly' THEN plans.price_yearly_cents / 12 ELSE plans.price_monthly_cents END) as mrr_cents")
            ->value('mrr_cents') ?? 0;

        $revenueThisMonthCents = PaymentTransaction::where('status', 'successful')
            ->whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->sum('amount_cents');

        return response()->json([
            'mrr_cents' => (int) $mrrCents,
            'revenue_this_month_cents' => (int) $revenueThisMonthCents,
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'total_users' => User::whereNull('agency_owner_id')->count(),
        ]);
    }
}
