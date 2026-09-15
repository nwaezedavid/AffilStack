<?php

namespace App\Filament\Resources\AgentTasks\Pages;

use App\Filament\Resources\AgentTasks\AgentTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListAgentTasks extends ListRecords
{
    protected static string $resource = AgentTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
