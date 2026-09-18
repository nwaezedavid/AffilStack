<?php

namespace App\Filament\Resources\CreditPackages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CreditPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->required(),
                        TextInput::make('credits')->required()->numeric()->live(onBlur: true),
                        Textarea::make('description')->columnSpanFull(),
                        TextInput::make('price_cents')
                            ->label('Price (USD)')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText(function (Get $get) {
                                $credits = (float) $get('credits');
                                $price = (float) $get('price_cents');

                                if ($credits <= 0 || $price <= 0) {
                                    return null;
                                }

                                return '$'.number_format($price / $credits, 4).' per credit.';
                            })
                            ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                        TextInput::make('currency')->required()->default('USD')->maxLength(3),
                        TextInput::make('sort_order')->required()->numeric()->default(0),
                        Toggle::make('is_featured')->label('Highlight as "best value"'),
                        Toggle::make('is_active')->default(true),
                    ]),
            ]);
    }
}
