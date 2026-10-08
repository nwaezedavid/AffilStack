<?php

namespace App\Filament\Resources\FeaturePages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\FeaturePages\Pages\CreateFeaturePage;
use App\Filament\Resources\FeaturePages\Pages\EditFeaturePage;
use App\Filament\Resources\FeaturePages\Pages\ListFeaturePages;
use App\Filament\Resources\FeaturePages\Schemas\FeaturePageForm;
use App\Filament\Resources\FeaturePages\Tables\FeaturePagesTable;
use App\Models\FeaturePage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Website → Features: one public page per tool (/features/{slug}) and its
 * entry in the header's Tools menu — every word, screenshot and video slot.
 */
class FeaturePageResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static ?string $model = FeaturePage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Features (tool pages)';

    protected static ?string $modelLabel = 'feature page';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FeaturePageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeaturePagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeaturePages::route('/'),
            'create' => CreateFeaturePage::route('/create'),
            'edit' => EditFeaturePage::route('/{record}/edit'),
        ];
    }
}
