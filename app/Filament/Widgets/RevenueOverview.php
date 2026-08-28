<?php

namespace App\Filament\Widgets;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $mrr = Subscription::query()
            ->where('status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw("
                SUM(CASE WHEN billing_cycle = 'yearly' THEN plans.price_yearly_cents / 12 ELSE plans.price_monthly_cents END) as mrr_cents
            ")
            ->value('mrr_cents') ?? 0;

        $revenueThisMonth = PaymentTransaction::where('status', 'successful')
            ->whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->sum('amount_cents');

        $activeSubs = Subscription::where('status', 'active')->count();
        $totalUsers = User::count();
        $newThisWeek = User::where('created_at', '>=', now()->subWeek())->count();

        return [
            Stat::make('MRR', '$'.number_format($mrr / 100, 2))
                ->description('Monthly recurring revenue, active subscriptions')
                ->color('success'),
            Stat::make('Revenue this month', '$'.number_format($revenueThisMonth / 100, 2))
                ->description('Successful Flutterwave transactions')
                ->color('success'),
            Stat::make('Active subscriptions', number_format($activeSubs))
                ->description($totalUsers.' total users'),
            Stat::make('New users this week', number_format($newThisWeek))
                ->description('Signups in the last 7 days'),
        ];
    }
}
