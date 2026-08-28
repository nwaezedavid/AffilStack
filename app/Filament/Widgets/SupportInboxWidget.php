<?php

namespace App\Filament\Widgets;

use App\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupportInboxWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $open = SupportTicket::whereIn('status', ['open', 'pending'])->count();
        $unassigned = SupportTicket::whereIn('status', ['open', 'pending'])->whereNull('assigned_to')->count();

        $oldestUnanswered = SupportTicket::where('status', 'open')
            ->orderBy('last_reply_at')
            ->first();

        $avgCsat = SupportTicket::whereNotNull('csat_rating')->avg('csat_rating');

        return [
            Stat::make('Open tickets', number_format($open))
                ->description($unassigned.' unassigned')
                ->color($open > 0 ? 'warning' : 'success'),
            Stat::make('Oldest unanswered', $oldestUnanswered ? $oldestUnanswered->last_reply_at->diffForHumans() : 'None')
                ->description($oldestUnanswered ? $oldestUnanswered->subject : 'Inbox is clear')
                ->color($oldestUnanswered ? 'danger' : 'success'),
            Stat::make('Avg. CSAT', $avgCsat ? number_format($avgCsat, 1).' / 5' : '—')
                ->description('From resolved tickets rated by customers'),
        ];
    }
}
