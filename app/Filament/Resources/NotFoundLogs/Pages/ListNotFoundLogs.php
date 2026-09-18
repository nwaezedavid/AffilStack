<?php

namespace App\Filament\Resources\NotFoundLogs\Pages;

use App\Filament\Resources\NotFoundLogs\NotFoundLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotFoundLogs extends ListRecords
{
    protected static string $resource = NotFoundLogResource::class;
}
