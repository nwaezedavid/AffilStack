<?php

namespace App\Filament\Resources\BannerAds\Tables;

use App\Models\AffiliateBannerImpression;
use App\Models\BannerAd;
use App\Models\ReferralClick;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class BannerAdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (BannerAd $record) => $record->headline),
                TextColumn::make('status')
                    ->state(fn (BannerAd $record) => $record->statusLabel())
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Live' => 'success',
                        'Scheduled' => 'info',
                        'Ended' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('discount_badge')->label('Discount')->placeholder('—'),
                TextColumn::make('ends_at')->label('Ends')->dateTime('M j, Y g:ia')->placeholder('—')->sortable(),
                TextColumn::make('views')
                    ->label('Views')
                    ->state(fn (BannerAd $record) => (int) AffiliateBannerImpression::query()->where('banner_key', 'like', 'ad'.$record->id.'-%')->sum('impressions'))
                    ->numeric(),
                TextColumn::make('clicks')
                    ->label('Clicks')
                    ->state(fn (BannerAd $record) => ReferralClick::query()->where('banner_key', 'like', 'ad'.$record->id.'-%')->count())
                    ->numeric(),
                ToggleColumn::make('is_active')->label('On'),
            ])
            ->reorderable('sort_order')
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (BannerAd $record) => 'Preview: '.$record->name)
                    ->modalWidth('6xl')
                    ->modalContent(fn (BannerAd $record) => view('filament.banner-ads.preview', [
                        'frames' => collect($record->availableSizes())->map(fn (string $size) => [
                            'size' => $size,
                            'width' => (int) config("affiliate_kit.sizes.{$size}.width"),
                            'height' => (int) config("affiliate_kit.sizes.{$size}.height"),
                            'url' => URL::temporarySignedRoute('embed.banner', now()->addHour(), ['size' => $size, 'design' => 'ad'.$record->id, 'preview' => 1]),
                        ]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
