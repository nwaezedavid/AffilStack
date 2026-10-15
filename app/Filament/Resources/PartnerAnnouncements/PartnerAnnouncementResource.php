<?php

namespace App\Filament\Resources\PartnerAnnouncements;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\PartnerAnnouncements\Pages\CreatePartnerAnnouncement;
use App\Filament\Resources\PartnerAnnouncements\Pages\EditPartnerAnnouncement;
use App\Filament\Resources\PartnerAnnouncements\Pages\ListPartnerAnnouncements;
use App\Filament\Resources\PartnerAnnouncements\Schemas\PartnerAnnouncementForm;
use App\Filament\Resources\PartnerAnnouncements\Tables\PartnerAnnouncementsTable;
use App\Models\PartnerAnnouncement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * News for partners: shown on the Partner Portal (with an unread badge)
 * and, when the admin chooses, emailed to every active partner.
 */
class PartnerAnnouncementResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = PartnerAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Partners';

    protected static ?string $navigationLabel = 'Partner News';

    protected static ?string $modelLabel = 'announcement';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PartnerAnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerAnnouncementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerAnnouncements::route('/'),
            'create' => CreatePartnerAnnouncement::route('/create'),
            'edit' => EditPartnerAnnouncement::route('/{record}/edit'),
        ];
    }
}
