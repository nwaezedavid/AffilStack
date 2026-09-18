<?php

namespace App\Filament\Resources\Tutorials\Pages;

use App\Filament\Resources\Tutorials\TutorialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTutorial extends CreateRecord
{
    protected static string $resource = TutorialResource::class;
}
