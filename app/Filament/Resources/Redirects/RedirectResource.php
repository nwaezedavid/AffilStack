<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Resources\Redirects\Schemas\RedirectForm;
use App\Filament\Resources\Redirects\Tables\RedirectsTable;
use App\Models\Redirect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * RankMath-style redirects manager — a dead/changed URL gets sent somewhere
 * real instead of 404ing. Applied at runtime by RedirectFallbackController
 * (see Redirect::forPath()'s cache). The 404 Monitor page (NotFoundLogs)
 * surfaces real dead-link patterns an admin can turn into a redirect here.
 */
class RedirectResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Redirects';

    protected static ?string $recordTitleAttribute = 'from_path';

    public static function form(Schema $schema): Schema
    {
        return RedirectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RedirectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
