<?php

namespace App\Filament\Resources\SwipeFileEntries;

use App\Filament\Resources\SwipeFileEntries\Pages\CreateSwipeFileEntry;
use App\Filament\Resources\SwipeFileEntries\Pages\EditSwipeFileEntry;
use App\Filament\Resources\SwipeFileEntries\Pages\ListSwipeFileEntries;
use App\Filament\Resources\SwipeFileEntries\Schemas\SwipeFileEntryForm;
use App\Filament\Resources\SwipeFileEntries\Tables\SwipeFileEntriesTable;
use App\Models\SwipeFileEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The swipe file library: admin-curated hooks, subject lines, and
 * thumbnail styles by niche. Powers the public /swipe-files browse page —
 * see SwipeFileEntry for why this is curated content rather than another
 * AI-generation module.
 */
class SwipeFileEntryResource extends Resource
{
    protected static ?string $model = SwipeFileEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SwipeFileEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SwipeFileEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSwipeFileEntries::route('/'),
            'create' => CreateSwipeFileEntry::route('/create'),
            'edit' => EditSwipeFileEntry::route('/{record}/edit'),
        ];
    }
}
