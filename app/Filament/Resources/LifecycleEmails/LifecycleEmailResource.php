<?php

namespace App\Filament\Resources\LifecycleEmails;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\LifecycleEmails\Pages\CreateLifecycleEmail;
use App\Filament\Resources\LifecycleEmails\Pages\EditLifecycleEmail;
use App\Filament\Resources\LifecycleEmails\Pages\ListLifecycleEmails;
use App\Filament\Resources\LifecycleEmails\RelationManagers\LogsRelationManager;
use App\Filament\Resources\LifecycleEmails\Schemas\LifecycleEmailForm;
use App\Filament\Resources\LifecycleEmails\Tables\LifecycleEmailsTable;
use App\Models\LifecycleEmail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Birthday, festive-season and re-engagement emails that run on autopilot
 * (see LifecycleEmailDispatcher). Every preset (LifecycleEmailPresets) is
 * a row here the admin can rewrite, re-date, restyle or switch off at any
 * time, with a live preview and "Send test to me". Lives with Support
 * because Sam (the Support Agent) owns outbound user communication.
 */
class LifecycleEmailResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'support';

    protected static ?string $model = LifecycleEmail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Lifecycle Emails';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LifecycleEmailForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LifecycleEmailsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLifecycleEmails::route('/'),
            'create' => CreateLifecycleEmail::route('/create'),
            'edit' => EditLifecycleEmail::route('/{record}/edit'),
        ];
    }
}
