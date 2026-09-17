<?php

namespace App\Filament\Resources\ReferralPayouts;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\ReferralPayouts\Pages\ListReferralPayouts;
use App\Filament\Resources\ReferralPayouts\Tables\ReferralPayoutsTable;
use App\Models\ReferralPayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The affiliate payout workflow — see App\Services\Referrals\ReferralPayoutService.
 * Rows are created only when an affiliate requests a payout from their
 * dashboard; an admin here either marks one paid (with a reference) or
 * rejects it. This is the ONLY place a ReferralEvent ever reaches "paid" —
 * see ReferralEventResource for the raw commission ledger this feeds from.
 */
class ReferralPayoutResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = ReferralPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Referral Payouts';

    public static function table(Table $table): Table
    {
        return ReferralPayoutsTable::configure($table);
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
            'index' => ListReferralPayouts::route('/'),
        ];
    }
}
