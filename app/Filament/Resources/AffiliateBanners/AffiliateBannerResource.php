<?php

namespace App\Filament\Resources\AffiliateBanners;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\AffiliateBanners\Pages\CreateAffiliateBanner;
use App\Filament\Resources\AffiliateBanners\Pages\EditAffiliateBanner;
use App\Filament\Resources\AffiliateBanners\Pages\ListAffiliateBanners;
use App\Filament\Resources\AffiliateBanners\Schemas\AffiliateBannerForm;
use App\Filament\Resources\AffiliateBanners\Tables\AffiliateBannersTable;
use App\Models\AffiliateBanner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Custom banner creatives (PNG/JPG/GIF) for the affiliate Marketing Kit.
 * Uploads appear first in every affiliate's banner gallery, ahead of the
 * built-in designs, and are preferred by the rotating embed for their size.
 * Every affiliate's tracking link is added automatically — admins only
 * upload the image.
 */
class AffiliateBannerResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = AffiliateBanner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Affiliate Banners';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AffiliateBannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateBannersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateBanners::route('/'),
            'create' => CreateAffiliateBanner::route('/create'),
            'edit' => EditAffiliateBanner::route('/{record}/edit'),
        ];
    }
}
