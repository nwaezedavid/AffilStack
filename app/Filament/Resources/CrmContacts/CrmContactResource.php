<?php

namespace App\Filament\Resources\CrmContacts;

use App\Filament\Resources\CrmContacts\Pages\EditCrmContact;
use App\Filament\Resources\CrmContacts\Pages\ListCrmContacts;
use App\Filament\Resources\CrmContacts\Schemas\CrmContactForm;
use App\Filament\Resources\CrmContacts\Tables\CrmContactsTable;
use App\Models\CrmContact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CrmContactResource extends Resource
{
    protected static ?string $model = CrmContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM Oversight';

    protected static ?string $navigationLabel = 'All Contacts';

    public static function form(Schema $schema): Schema
    {
        return CrmContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmContactsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmContacts::route('/'),
            'edit' => EditCrmContact::route('/{record}/edit'),
        ];
    }
}
