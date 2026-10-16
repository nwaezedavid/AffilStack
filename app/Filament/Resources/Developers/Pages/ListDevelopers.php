<?php

namespace App\Filament\Resources\Developers\Pages;

use App\Filament\Resources\Developers\DeveloperResource;
use Filament\Resources\Pages\ListRecords;

class ListDevelopers extends ListRecords
{
    protected static string $resource = DeveloperResource::class;
}
