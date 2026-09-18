<?php

namespace App\Filament\Resources\CreditPackages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\CreditPackages\Pages\CreateCreditPackage;
use App\Filament\Resources\CreditPackages\Pages\EditCreditPackage;
use App\Filament\Resources\CreditPackages\Pages\ListCreditPackages;
use App\Filament\Resources\CreditPackages\Schemas\CreditPackageForm;
use App\Filament\Resources\CreditPackages\Tables\CreditPackagesTable;
use App\Models\CreditPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Admin-editable one-time credit top-up packages — "do the maths and setup
 * the appropriate price for extra tokens purchase". See CreditPackagesSeeder
 * for the pricing rationale and CreditTopupController for the public
 * checkout flow this data drives.
 */
class CreditPackageResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = CreditPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Credit Top-Ups';

    public static function form(Schema $schema): Schema
    {
        return CreditPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditPackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditPackages::route('/'),
            'create' => CreateCreditPackage::route('/create'),
            'edit' => EditCreditPackage::route('/{record}/edit'),
        ];
    }
}
