<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Models\Plan;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                            ->live(onBlur: true)
                            ->formatStateUsing(fn ($state) => $state !== null ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                        TextInput::make('price_yearly_cents')
                            ->label('Yearly price (USD)')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->required()
                            ->helperText(function (Get $get) {
                                if (! is_numeric($get('price_monthly_cents'))) {
                                    return 'Item #2: annual billing should be exactly 15% off — set the monthly price first for a suggestion.';
                                }

                                $suggested = Plan::yearlyPriceCentsFor((int) round(((float) $get('price_monthly_cents')) * 100)) / 100;

                                return '15% off 12 months of the monthly price above would be $'.number_format($suggested, 2).'/yr.';
                            })
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
                        Select::make('seat_mode')
                            ->label('Team seat model')
                            ->helperText('Isolated: each seat is scoped to one offer, draft-only (the original agency model). Shared: every seat sees and works every offer, with a per-seat member/manager role — the Business tier.')
                            ->options([
                                'isolated' => 'Isolated (agency — one offer per seat)',
                                'shared' => 'Shared (business — whole team, one offer pool)',
                            ])
                            ->required()
                            ->default('isolated')
                            ->columnSpanFull(),
                        Toggle::make('is_featured')->label('Highlight as "most popular"'),
                        Toggle::make('is_active')->default(true),
                    ]),

                Section::make('Access')
                    ->components([
                        CheckboxList::make('channels')
                            ->options(Plan::channelLabels())
                            ->columns(4),
                        KeyValue::make('features')
                            ->label('Extra feature flags (optional)')
                            ->keyLabel('Flag')
                            ->valueLabel('Value'),
                    ]),
            ]);
    }
}
