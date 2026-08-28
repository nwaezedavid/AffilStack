<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->required(),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true),
                        Textarea::make('description')->columnSpanFull(),
                        TextInput::make('price_monthly_cents')
                            ->label('Monthly price (USD)')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                        TextInput::make('price_yearly_cents')
                            ->label('Yearly price (USD)')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                        TextInput::make('currency')->required()->default('USD')->maxLength(3),
                        TextInput::make('sort_order')->required()->numeric()->default(0),
                    ]),

                Section::make('Limits')
                    ->columns(3)
                    ->components([
                        TextInput::make('credits_per_month')->required()->numeric(),
                        TextInput::make('active_products_limit')
                            ->label('Active products (0 = unlimited)')
                            ->required()->numeric()->default(1),
                        TextInput::make('contact_limit')
                            ->label('CRM contact limit (0 = unlimited)')
                            ->required()->numeric(),
                        TextInput::make('team_seats')->required()->numeric()->default(1),
                        Toggle::make('is_featured')->label('Highlight as "most popular"'),
                        Toggle::make('is_active')->default(true),
                    ]),

                Section::make('Access')
                    ->components([
                        CheckboxList::make('channels')
                            ->options([
                                'research' => 'Offer research',
                                'blog' => 'Blog / Medium articles',
                                'linkedin' => 'LinkedIn',
                                'youtube' => 'YouTube',
                                'ugc' => 'UGC',
                                'pinterest' => 'Pinterest',
                                'google_maps' => 'Google Maps CRM',
                            ])
                            ->columns(4),
                        KeyValue::make('features')
                            ->label('Extra feature flags (optional)')
                            ->keyLabel('Flag')
                            ->valueLabel('Value'),
                    ]),
            ]);
    }
}
