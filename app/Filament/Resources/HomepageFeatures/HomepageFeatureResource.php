<?php

namespace App\Filament\Resources\HomepageFeatures;

use App\Filament\Resources\HomepageFeatures\Pages\CreateHomepageFeature;
use App\Filament\Resources\HomepageFeatures\Pages\EditHomepageFeature;
use App\Filament\Resources\HomepageFeatures\Pages\ListHomepageFeatures;
use App\Filament\Resources\HomepageFeatures\Schemas\HomepageFeatureForm;
use App\Filament\Resources\HomepageFeatures\Tables\HomepageFeaturesTable;
use App\Models\HomepageFeature;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Feature cards shown on the public homepage (Phase 4 foundation: homepage
 * redesign) — add, remove, or reorder without a code deploy.
 */
class HomepageFeatureResource extends Resource
{
    protected static ?string $model = HomepageFeature::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return HomepageFeatureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomepageFeaturesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomepageFeatures::route('/'),
            'create' => CreateHomepageFeature::route('/create'),
            'edit' => EditHomepageFeature::route('/{record}/edit'),
        ];
    }
}
