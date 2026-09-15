<?php

namespace App\Filament\Resources\CreativeTasks;

use App\Filament\Resources\CreativeTasks\Pages\ListCreativeTasks;
use App\Filament\Resources\CreativeTasks\Tables\CreativeTasksTable;
use App\Models\AgentTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tony's (the Creative Agent's) own actionable view over the shared
 * agent_tasks table — scoped to agent=creative. Requesting a draft,
 * previewing it, and approving-or-declining all happen here; the
 * cross-agent AgentTaskResource stays the read-only log of every agent's
 * history, unchanged.
 */
class CreativeTaskResource extends Resource
{
    protected static ?string $model = AgentTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Creative Studio';

    protected static ?string $slug = 'creative-tasks';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('agent', AgentTask::AGENT_CREATIVE);
    }

    public static function table(Table $table): Table
    {
        return CreativeTasksTable::configure($table);
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
            'index' => ListCreativeTasks::route('/'),
        ];
    }
}
