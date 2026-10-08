<?php

namespace App\Filament\Resources\AnalyticsSegments\Schemas;

use App\Models\AnalyticsProfile;
use App\Services\Analytics\SegmentEvaluator;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AnalyticsSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        $operatorLabels = ['in' => 'is any of', 'not_in' => 'is none of', 'is' => 'is', 'gte' => 'at least', 'lte' => 'at most', 'contains' => 'contains'];

        return $schema
            ->components([
                Section::make('Audience')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),
                        Textarea::make('description')->rows(2)->maxLength(500)->columnSpanFull()
                            ->helperText('Who this is and what you\'ll use it for — shown on the audiences list.'),
                        Select::make('purpose')
                            ->options(['target' => 'Target — show ads to these people', 'exclude' => 'Exclude — leave these people out of ads'])
                            ->default('target')
                            ->required(),
                    ]),

                Section::make('Rules')
                    ->description('A person must match EVERY rule. Lifecycle stages: '.implode(', ', array_keys(AnalyticsProfile::STAGES)).'. Common events: pricing_viewed, plan_selected, checkout_started, signup_completed, cta_click, login, research_started, content_generated.')
                    ->components([
                        Repeater::make('rules')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('field')
                                    ->label('When')
                                    ->options(collect(SegmentEvaluator::FIELDS)->map(fn ($f) => $f['label'])->all())
                                    ->required()
                                    ->live(),
                                Select::make('operator')
                                    ->options(fn (Get $get) => collect(SegmentEvaluator::FIELDS[$get('field')]['operators'] ?? ['is'])->mapWithKeys(fn ($op) => [$op => $operatorLabels[$op]])->all())
                                    ->required()
                                    ->default('is'),
                                TextInput::make('value')
                                    ->required()
                                    ->maxLength(200)
                                    ->helperText(fn (Get $get) => SegmentEvaluator::FIELDS[$get('field')]['help'] ?? 'Separate several values with commas.'),
                                TextInput::make('days')
                                    ->label('Within (days)')
                                    ->numeric()->minValue(1)->maxValue(400)->default(30)
                                    ->visible(fn (Get $get) => in_array($get('field'), ['did_event', 'not_did_event'], true)),
                            ])
                            ->columns(4)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add rule'),
                    ]),
            ]);
    }
}
