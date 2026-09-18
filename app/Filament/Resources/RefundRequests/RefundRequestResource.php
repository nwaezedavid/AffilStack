<?php

namespace App\Filament\Resources\RefundRequests;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\RefundRequests\Pages\ListRefundRequests;
use App\Filament\Resources\RefundRequests\Tables\RefundRequestsTable;
use App\Models\RefundRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Audit item #8 — read-only audit trail for the fully automatic refund
 * policy (see RefundEligibilityService/RefundExecutionService). No admin
 * action happens here on purpose: every row already reflects something the
 * system decided on its own strict, non-discretionary rule, so there's
 * nothing for an admin to approve, edit, or delete — this page exists only
 * so refunds are visible without needing to query the database directly.
 */
class RefundRequestResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = RefundRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Refund Requests';

    protected static ?string $modelLabel = 'refund request';

    public static function table(Table $table): Table
    {
        return RefundRequestsTable::configure($table);
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
            'index' => ListRefundRequests::route('/'),
        ];
    }
}
