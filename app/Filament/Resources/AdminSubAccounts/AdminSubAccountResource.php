<?php

namespace App\Filament\Resources\AdminSubAccounts;

use App\Filament\Resources\AdminSubAccounts\Pages\CreateAdminSubAccount;
use App\Filament\Resources\AdminSubAccounts\Pages\EditAdminSubAccount;
use App\Filament\Resources\AdminSubAccounts\Pages\ListAdminSubAccounts;
use App\Filament\Resources\AdminSubAccounts\Schemas\AdminSubAccountForm;
use App\Filament\Resources\AdminSubAccounts\Tables\AdminSubAccountsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin sub-accounts: department-scoped staff logins (role "admin_sub")
 * that see only the Filament nav groups/resources covered by their granted
 * departments (config('admin.departments')) — see
 * App\Filament\Concerns\ScopedToDepartment and User::canAccessDepartment().
 *
 * Deliberately super-admin-only, always — this is the one place that
 * grants panel access at all, so it never itself becomes department-
 * scoped (a sub-account can never create or edit other sub-accounts,
 * regardless of which departments it holds).
 */
class AdminSubAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Users & Access';

    protected static ?string $navigationLabel = 'Admin Sub-Accounts';

    protected static ?string $modelLabel = 'admin sub-account';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('admin_sub');
    }

    public static function form(Schema $schema): Schema
    {
        return AdminSubAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminSubAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminSubAccounts::route('/'),
            'create' => CreateAdminSubAccount::route('/create'),
            'edit' => EditAdminSubAccount::route('/{record}/edit'),
        ];
    }
}
