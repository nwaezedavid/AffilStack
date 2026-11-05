<?php

namespace App\Filament\Resources\TrialInvites\Pages;

use App\Filament\Resources\TrialInvites\TrialInviteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrialInvites extends ListRecords
{
    protected static string $resource = TrialInviteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Send trial invite'),
        ];
    }
}
