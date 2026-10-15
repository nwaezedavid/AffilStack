<?php

namespace App\Filament\Resources\BannerAds;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\BannerAds\Pages\CreateBannerAd;
use App\Filament\Resources\BannerAds\Pages\EditBannerAd;
use App\Filament\Resources\BannerAds\Pages\ListBannerAds;
use App\Filament\Resources\BannerAds\Schemas\BannerAdForm;
use App\Filament\Resources\BannerAds\Tables\BannerAdsTable;
use App\Models\BannerAd;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Auto-updating banner ads. Partners paste the banner script or iframe
 * once; whatever is live here (headline, message, discount, image, theme,
 * landing page, schedule) is what their visitors see, with the current
 * logo from Brand Settings.
 */
class BannerAdResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = BannerAd::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    protected static ?string $navigationLabel = 'Banner Ads';

    protected static ?string $modelLabel = 'banner ad';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BannerAdForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BannerAdsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBannerAds::route('/'),
            'create' => CreateBannerAd::route('/create'),
            'edit' => EditBannerAd::route('/{record}/edit'),
        ];
    }
}
