<?php

namespace App\Filament\Resources\AffiliateApplications;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\AffiliateApplications\Pages\ListAffiliateApplications;
use App\Filament\Resources\AffiliateApplications\Tables\AffiliateApplicationsTable;
use App\Models\AffiliateApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * "Anyone can sign-up to become an affiliate without first becoming a user
 * of the platform... others will have to be manually approved by the admin
 * after they submit their application" — every submission from the public
 * affiliate landing page lands here. See AffiliateApplicationService for
 * what Approve/Reject actually do.
 */
class AffiliateApplicationResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = AffiliateApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Affiliate Applications';

    public static function table(Table $table): Table
    {
        return AffiliateApplicationsTable::configure($table);
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
            'index' => ListAffiliateApplications::route('/'),
        ];
    }
}
