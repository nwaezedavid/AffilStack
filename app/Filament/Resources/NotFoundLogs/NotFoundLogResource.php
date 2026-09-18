<?php

namespace App\Filament\Resources\NotFoundLogs;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\NotFoundLogs\Pages\ListNotFoundLogs;
use App\Filament\Resources\NotFoundLogs\Tables\NotFoundLogsTable;
use App\Models\NotFoundLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * RankMath's "404 Monitor" — a read-only, deduped, capped list of paths
 * that 404'd on the public site (see NotFoundLog, written from
 * RedirectFallbackController). No create/edit form: an admin either
 * ignores a stray hit or turns it into a real Redirect via the row action.
 */
class NotFoundLogResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $model = NotFoundLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = '404 Monitor';

    protected static ?string $recordTitleAttribute = 'path';

    public static function table(Table $table): Table
    {
        return NotFoundLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotFoundLogs::route('/'),
        ];
    }
}
