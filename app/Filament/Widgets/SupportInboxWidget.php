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

        // A ticket with no reply yet (last_reply_at is null) has been waiting
        // since it was created, so it must sort as if that were its "last
        // reply" time — not be excluded, and not crash on a null diffForHumans().
        $oldestUnanswered = SupportTicket::where('status', 'open')
            ->orderByRaw('COALESCE(last_reply_at, created_at) asc')
            ->first();

        $avgCsat = SupportTicket::whereNotNull('csat_rating')->avg('csat_rating');

        return [
            Stat::make('Open tickets', number_format($open))
                ->description($unassigned.' unassigned')
                ->color($open > 0 ? 'warning' : 'success'),
            Stat::make('Oldest unanswered', $oldestUnanswered ? ($oldestUnanswered->last_reply_at ?? $oldestUnanswered->created_at)->diffForHumans() : 'None')
                ->description($oldestUnanswered ? $oldestUnanswered->subject : 'Inbox is clear')
                ->color($oldestUnanswered ? 'danger' : 'success'),
            Stat::make('Avg. CSAT', $avgCsat ? number_format($avgCsat, 1).' / 5' : '—')
                ->description('From resolved tickets rated by customers'),
        ];
    }
}
