<?php

namespace App\Filament\Resources\AffiliateSwipes\Schemas;

use App\Models\AffiliateSwipe;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AffiliateSwipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->options(AffiliateSwipe::TYPES)
                    ->required()
                    ->default('email')
                    ->live(),
                Select::make('platform')
                    ->options(config('affiliate_kit.social_platforms'))
                    ->visible(fn (Get $get): bool => $get('type') === 'social')
                    ->required(fn (Get $get): bool => $get('type') === 'social'),
                TextInput::make('title')
                    ->required()
                    ->maxLength(150)
                    ->helperText('Internal label shown above the copy, e.g. "Beginner-friendly".')
                    ->columnSpanFull(),
                TextInput::make('subject')
                    ->label('Subject line')
                    ->maxLength(200)
                    ->visible(fn (Get $get): bool => $get('type') === 'email')
                    ->required(fn (Get $get): bool => $get('type') === 'email')
                    ->columnSpanFull(),
                Textarea::make('body')
                    ->label(fn (Get $get): string => $get('type') === 'text_link' ? 'Anchor text' : 'Body')
                    ->rows(14)
                    ->required()
                    ->helperText('Placeholders: {affiliate_link} (the affiliate\'s tracked link), {first_name} (the reader — affiliates swap it for their email tool\'s merge tag), {your_name} (the affiliate). For text links, write just the anchor text.')
                    ->columnSpanFull(),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->label('Active')->default(true),
            ]);
    }
}
