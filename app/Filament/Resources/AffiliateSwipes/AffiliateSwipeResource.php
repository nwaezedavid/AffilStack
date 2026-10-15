<?php

namespace App\Filament\Resources\AffiliateSwipes;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\AffiliateSwipes\Pages\CreateAffiliateSwipe;
use App\Filament\Resources\AffiliateSwipes\Pages\EditAffiliateSwipe;
use App\Filament\Resources\AffiliateSwipes\Pages\ListAffiliateSwipes;
use App\Filament\Resources\AffiliateSwipes\Schemas\AffiliateSwipeForm;
use App\Filament\Resources\AffiliateSwipes\Tables\AffiliateSwipesTable;
use App\Models\AffiliateSwipe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The promo copy in the affiliate Marketing Kit — email swipes, social posts
 * and text links — with {affiliate_link}, {first_name} and {your_name}
 * filled in per affiliate. See AffiliateSwipeSeeder for the presets.
 */
class AffiliateSwipeResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = AffiliateSwipe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    protected static ?string $navigationLabel = 'Affiliate Swipes';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AffiliateSwipeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateSwipesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateSwipes::route('/'),
            'create' => CreateAffiliateSwipe::route('/create'),
            'edit' => EditAffiliateSwipe::route('/{record}/edit'),
        ];
    }
}
