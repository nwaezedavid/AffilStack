<?php

namespace App\Filament\Resources\CannedReplies\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CannedReplyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->helperText('Shown in the picker when staff reply to a ticket.'),
                Textarea::make('body')->required()->rows(6),
            ]);
    }
}
