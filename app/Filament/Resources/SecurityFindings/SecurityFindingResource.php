<?php

namespace App\Filament\Resources\SecurityFindings;

use App\Filament\Resources\SecurityFindings\Pages\ListSecurityFindings;
use App\Filament\Resources\SecurityFindings\Tables\SecurityFindingsTable;
use App\Models\SecurityFinding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Tom's (the Security Agent's) findings — see SecurityScanService. Rows are
 * only ever created by the daily scan; a super-admin's only actions here
 * are reviewing the AI's analysis, approving-and-scheduling a fix, or
 * dismissing a finding that's been handled some other way.
 */
class SecurityFindingResource extends Resource
{
    protected static ?string $model = SecurityFinding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Agents';

    protected static ?string $navigationLabel = 'Security Center';

    public static function table(Table $table): Table
    {
        return SecurityFindingsTable::configure($table);
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
            'index' => ListSecurityFindings::route('/'),
        ];
    }
}
