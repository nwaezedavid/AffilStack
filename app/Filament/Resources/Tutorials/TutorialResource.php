<?php

namespace App\Filament\Resources\Tutorials;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\Tutorials\Pages\CreateTutorial;
use App\Filament\Resources\Tutorials\Pages\EditTutorial;
use App\Filament\Resources\Tutorials\Pages\ListTutorials;
use App\Filament\Resources\Tutorials\Schemas\TutorialForm;
use App\Filament\Resources\Tutorials\Tables\TutorialsTable;
use App\Models\Tutorial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * "Create a dedicated menu in the admin dashboard area where I can create a
 * tutorial topic/title, and then add the YouTube video link to be embedded
 * in the frontend for the public." See TutorialController for the public
 * /learn "Learning Centre" page this drives.
 */
class TutorialResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static ?string $model = Tutorial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TutorialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TutorialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTutorials::route('/'),
            'create' => CreateTutorial::route('/create'),
            'edit' => EditTutorial::route('/{record}/edit'),
        ];
    }
}
