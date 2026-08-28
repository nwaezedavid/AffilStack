<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(4)
                ->components([
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('category')->badge(),
                    TextEntry::make('priority')->badge(),
                    TextEntry::make('status')->badge(),
                ]),
        ]);
    }
}
