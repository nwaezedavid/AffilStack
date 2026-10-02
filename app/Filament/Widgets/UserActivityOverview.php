<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\UserActivityDay;
use App\Support\Lifecycle\LifecycleSettings;
use App\Support\Lifecycle\UserActivity;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Active vs idle vs inactive users at a glance (admin dashboard and the
 * top of the Users list), with a 14-day daily-active-users sparkline from
 * user_activity_days. Each stat links to the matching Users tab.
 */
class UserActivityOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'User activity';

    public static function canView(): bool
    {
        return auth()->user()?->canAccessDepartment('users_access') ?? false;
    }

    protected function getStats(): array
    {
        $count = fn (string $status) => UserActivity::scope(UserResource::getEloquentQuery(), $status)->count();
        $active = $count(UserActivity::ACTIVE);
        $idle = $count(UserActivity::IDLE);
        $inactive = $count(UserActivity::INACTIVE);
        $never = $count(UserActivity::NEVER);
        $total = max(1, $active + $idle + $inactive + $never);

        $since = CarbonImmutable::today()->subDays(13);
        $daily = UserActivityDay::query()
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn ($total, $date) => [CarbonImmutable::parse($date)->toDateString() => (int) $total]);
        $sparkline = collect(range(0, 13))->map(fn (int $i) => $daily[$since->addDays($i)->toDateString()] ?? 0)->all();
        $today = end($sparkline) ?: 0;

        $url = fn (string $tab) => UserResource::getUrl('index', ['tab' => $tab]);

        return [
            Stat::make('Active users', number_format($active))
                ->description(round($active / $total * 100).'% · '.number_format($today).' active today')
                ->descriptionIcon('heroicon-m-signal')
                ->chart($sparkline)
                ->color('success')
                ->url($url(UserActivity::ACTIVE)),
            Stat::make('Idle', number_format($idle))
                ->description('Last seen '.LifecycleSettings::activeWithinDays().'–'.LifecycleSettings::inactiveAfterDays().' days ago')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('warning')
                ->url($url(UserActivity::IDLE)),
            Stat::make('Inactive', number_format($inactive))
                ->description('No activity for '.LifecycleSettings::inactiveAfterDays().'+ days')
                ->descriptionIcon('heroicon-m-moon')
                ->color('danger')
                ->url($url(UserActivity::INACTIVE)),
            Stat::make('Never logged in', number_format($never))
                ->description('Accounts with no sign-in recorded')
                ->descriptionIcon('heroicon-m-minus-circle')
                ->color('gray')
                ->url($url(UserActivity::NEVER)),
        ];
    }
}
