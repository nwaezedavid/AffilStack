<?php

namespace App\Filament\Widgets;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverview extends StatsOverviewWidget
{
    /** Revenue and subscription counts are billing information. */
    public static function canView(): bool
    {
        return auth()->user()?->canAccessDepartment('billing') ?? false;
    }

    protected function getStats(): array
    {
        $mrr = Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw("
                SUM(CASE WHEN billing_cycle = 'yearly' THEN plans.price_yearly_cents / 12 ELSE plans.price_monthly_cents END) as mrr_cents
            ")
            ->value('mrr_cents') ?? 0;

        // Summed per currency: Paystack settles in naira kobo, so adding
        // it to USD cents inflated "revenue" by the exchange rate.
        $revenueByCurrency = PaymentTransaction::where('status', 'successful')
            ->where('processed_at', '>=', now()->startOfMonth())
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount_cents) as total_cents')
            ->pluck('total_cents', 'currency');

        $revenueThisMonth = (int) ($revenueByCurrency['USD'] ?? 0);
        $otherCurrencies = $revenueByCurrency->except('USD')
            ->map(fn ($cents, $currency) => $currency.' '.number_format($cents / 100, 2))
            ->implode(' + ');

        $activeSubs = Subscription::where('status', 'active')->count();
        $totalUsers = User::count();
        $newThisWeek = User::where('created_at', '>=', now()->subWeek())->count();

        return [
            Stat::make('MRR', '$'.number_format($mrr / 100, 2))
                ->description('Monthly recurring revenue, active subscriptions')
                ->color('success'),
            Stat::make('Revenue this month', '$'.number_format($revenueThisMonth / 100, 2))
                ->description($otherCurrencies !== '' ? 'USD payments, plus '.$otherCurrencies : 'Successful USD payments')
                ->color('success'),
            Stat::make('Active subscriptions', number_format($activeSubs))
                ->description($totalUsers.' total users'),
            Stat::make('New users this week', number_format($newThisWeek))
                ->description('Signups in the last 7 days'),
        ];
    }
}
