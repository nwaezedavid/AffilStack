<?php

namespace App\Filament\Resources\ApiTokens;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Filament\Resources\ApiTokens\Tables\ApiTokensTable;
use App\Models\ApiToken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Task #6 (general API + team automation): read-only oversight of every
 * issued API token, platform-wide — plus, via the header action on the
 * list page, the only place a full admin can mint an 'admin'-type token
 * for the separate /v1/admin/* surface (see EnsureAdminApiToken). Ordinary
 * ('user'-type) tokens are still self-service from each user's own
 * dashboard (Extension / API Access pages) — this resource never creates
 * one of those, only observes and revokes.
 */
class ApiTokenResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'system';

    protected static ?string $model = ApiToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return ApiTokensTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiTokens::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
