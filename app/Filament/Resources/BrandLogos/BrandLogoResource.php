<?php

namespace App\Filament\Resources\BrandLogos;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\BrandLogos\Pages\CreateBrandLogo;
use App\Filament\Resources\BrandLogos\Pages\EditBrandLogo;
use App\Filament\Resources\BrandLogos\Pages\ListBrandLogos;
use App\Filament\Resources\BrandLogos\Schemas\BrandLogoForm;
use App\Filament\Resources\BrandLogos\Tables\BrandLogosTable;
use App\Models\BrandLogo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * "Make space/menu in the admin dashboard area where I can upload the
 * logos of each brand I've worked with (unlimited list)." Add, remove, or
 * reorder without a code deploy — see BrandLogo::activePublicList() and
 * the homepage marquee section it feeds.
 */
class BrandLogoResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static ?string $model = BrandLogo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Brand Logos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BrandLogoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandLogosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrandLogos::route('/'),
            'create' => CreateBrandLogo::route('/create'),
            'edit' => EditBrandLogo::route('/{record}/edit'),
        ];
    }
}
