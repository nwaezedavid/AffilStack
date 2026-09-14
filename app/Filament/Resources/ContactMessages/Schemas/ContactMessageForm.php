<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->disabled(),
                TextInput::make('email')->disabled(),
                TextInput::make('subject')->disabled()->columnSpanFull(),
                Textarea::make('message')->disabled()->rows(6)->columnSpanFull(),
                Select::make('status')
                    ->options(['new' => 'New', 'read' => 'Read', 'replied' => 'Replied'])
                    ->required(),
            ]);
    }
}
