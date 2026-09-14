<?php

namespace App\Filament\Resources\ScheduledTaskRuns;

use App\Filament\Resources\ScheduledTaskRuns\Pages\ListScheduledTaskRuns;
use App\Filament\Resources\ScheduledTaskRuns\Tables\ScheduledTaskRunsTable;
use App\Models\ScheduledTaskRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only history of every scheduled command run (see
 * App\Console\ScheduleMonitoring) — there's nothing for an admin to create
 * or edit here, only to observe and, occasionally, prune.
 */
class ScheduledTaskRunResource extends Resource
{
    protected static ?string $model = ScheduledTaskRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $recordTitleAttribute = 'task';

    public static function table(Table $table): Table
    {
        return ScheduledTaskRunsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledTaskRuns::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
