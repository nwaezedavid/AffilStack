<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Needs attention')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['open', 'pending']))
                ->badge(SupportTicket::whereIn('status', ['open', 'pending'])->count()),
            'all' => Tab::make('All tickets'),
        ];
    }
}
