<?php

namespace App\Filament\Resources\ReferralEvents;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\ReferralEvents\Pages\ListReferralEvents;
use App\Filament\Resources\ReferralEvents\Tables\ReferralEventsTable;
use App\Models\ReferralEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The referral commission ledger. Rows are created only by
 * ReferralService::recordCommission() (see PaymentProcessor) — nothing
 * here is hand-created. Admins move status pending -> approved -> paid
 * inline in the table, the same pattern SupportTicketsTable uses for
 * ticket status.
 */
class ReferralEventResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = ReferralEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Referral Payouts';

    public static function table(Table $table): Table
    {
        return ReferralEventsTable::configure($table);
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
            'index' => ListReferralEvents::route('/'),
        ];
    }
}
