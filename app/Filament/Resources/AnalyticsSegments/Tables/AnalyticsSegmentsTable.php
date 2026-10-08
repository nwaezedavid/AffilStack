<?php

namespace App\Filament\Resources\AnalyticsSegments\Tables;

use App\Models\AnalyticsSegment;
use App\Services\Analytics\SegmentEvaluator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnalyticsSegmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Audience')
                    ->weight('semibold')
                    ->description(fn (AnalyticsSegment $record) => $record->description)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('purpose')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'exclude' ? 'Exclusion list' : 'Target')
                    ->color(fn (string $state) => $state === 'exclude' ? 'warning' : 'info'),
                TextColumn::make('people')
                    ->label('People now')
                    ->state(fn (AnalyticsSegment $record) => app(SegmentEvaluator::class)->count($record))
                    ->numeric()
                    ->tooltip('Everyone with a profile who matches the rules right now (allowed analytics).'),
                TextColumn::make('exportable')
                    ->label('Can upload')
                    ->state(fn (AnalyticsSegment $record) => app(SegmentEvaluator::class)->exportableCount($record))
                    ->numeric()
                    ->color('success')
                    ->tooltip('Known by email AND allowed advertising cookies — these are the rows in the CSV.'),
                TextColumn::make('is_preset')
                    ->label('Type')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Preset' : 'Custom')
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('meta')
                        ->label('Download for Meta')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (AnalyticsSegment $record) => route('admin.analytics.export', ['type' => 'audience', 'segment' => $record->id, 'platform' => 'meta'])),
                    Action::make('google')
                        ->label('Download for Google Ads')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (AnalyticsSegment $record) => route('admin.analytics.export', ['type' => 'audience', 'segment' => $record->id, 'platform' => 'google'])),
                ])
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->button()
                    ->color('primary')
                    ->visible(fn (): bool => (bool) auth()->user()?->isFullAdmin()),
                EditAction::make(),
                DeleteAction::make()->visible(fn (AnalyticsSegment $record) => ! $record->is_preset),
            ]);
    }
}
