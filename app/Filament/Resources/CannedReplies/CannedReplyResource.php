<?php

namespace App\Filament\Resources\CannedReplies;

use App\Filament\Resources\CannedReplies\Pages\CreateCannedReply;
use App\Filament\Resources\CannedReplies\Pages\EditCannedReply;
use App\Filament\Resources\CannedReplies\Pages\ListCannedReplies;
use App\Filament\Resources\CannedReplies\Schemas\CannedReplyForm;
use App\Filament\Resources\CannedReplies\Tables\CannedRepliesTable;
use App\Models\CannedReply;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * A small library of reusable reply templates support staff can drop into a
 * ticket reply (see SupportTickets' MessagesRelationManager) and then edit
 * before sending. Sam (the Support Agent) also drafts new suggestions here
 * from recurring patterns in resolved tickets (SamAgentService::
 * suggestTemplates()) — those land with status=suggested and need a
 * staff/admin to activate them before they reach the picker.
 */
class CannedReplyResource extends Resource
{
    protected static ?string $model = CannedReply::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CannedReplyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CannedRepliesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCannedReplies::route('/'),
            'create' => CreateCannedReply::route('/create'),
            'edit' => EditCannedReply::route('/{record}/edit'),
        ];
    }
}
