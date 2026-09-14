<?php

namespace App\Filament\Resources\ScheduledTaskRuns\Pages;

use App\Filament\Resources\ScheduledTaskRuns\ScheduledTaskRunResource;
use Filament\Resources\Pages\ListRecords;

class ListScheduledTaskRuns extends ListRecords
{
    protected static string $resource = ScheduledTaskRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
