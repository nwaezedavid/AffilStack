<?php

namespace App\Filament\Resources\FaqItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FaqItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('question')->required()->columnSpanFull(),
                Textarea::make('answer')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull()
                    ->helperText('Keep it concise and factual — the AI support assistant answers word-for-word from this.'),
                Select::make('category')
                    ->options([
                        'general' => 'General',
                        'billing' => 'Billing',
                        'features' => 'Features',
                        'account' => 'Account',
                    ])
                    ->default('general')
                    ->required(),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_published')->default(true)->columnSpanFull(),
            ]);
    }
}
