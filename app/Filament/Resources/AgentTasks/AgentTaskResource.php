<?php

namespace App\Filament\Resources\AgentTasks;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\AgentTasks\Pages\ListAgentTasks;
use App\Filament\Resources\AgentTasks\Tables\AgentTasksTable;
use App\Models\AgentTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * A cross-agent audit log: every permission request any AI agent has ever
 * made, whatever came of it. Read-only by design — approval happens on the
 * agent's own resource (e.g. SecurityFindingResource), never here.
 */
class AgentTaskResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'ai_agents';

    protected static ?string $model = AgentTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Agent Task Log';

    public static function table(Table $table): Table
    {
        return AgentTasksTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgentTasks::route('/'),
        ];
    }
}
