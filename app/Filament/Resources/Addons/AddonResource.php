<?php

namespace App\Filament\Resources\Addons;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\Addons\Pages\ListAddons;
use App\Models\Addon;
use App\Models\ApiToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Feature 7: every add-on — feature the best ones, suspend one that breaks
 * the rules (it disappears from the marketplace and its app tokens stop
 * working at once).
 */
class AddonResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'marketplace';

    protected static ?string $model = Addon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Add-ons';

    protected static ?string $modelLabel = 'add-on';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['liveVersion', 'workingVersion', 'developer']))
            ->columns([
                TextColumn::make('slug')->label('Add-on')
                    ->formatStateUsing(fn ($state, Addon $record) => ($record->liveVersion ?? $record->workingVersion)?->name ?? $state)
                    ->description(fn (Addon $record) => $record->typeLabel().($record->liveVersion ? ' · '.$record->liveVersion->priceLabel() : ''))
                    ->searchable(),
                TextColumn::make('developer.display_name')->label('Developer')->searchable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'published' => 'success',
                    'suspended' => 'danger',
                    'retired' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('installs_count')->label('Installs')->numeric()->sortable(),
                ToggleColumn::make('is_featured')->label('Featured'),
                TextColumn::make('published_at')->since()->label('Live since')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'suspended' => 'Suspended', 'retired' => 'Retired']),
                SelectFilter::make('type')->options(collect(config('marketplace.types'))->map(fn ($type) => $type['label'])->all()),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('It disappears from the marketplace, apps lose access to customer accounts immediately, and subscriptions stop renewing.')
                    ->visible(fn (Addon $record) => $record->status !== 'suspended')
                    ->action(function (Addon $record) {
                        $record->update(['status' => 'suspended']);
                        $record->installs()->where('status', 'active')->where('price_cents', '>', 0)->update(['cancel_at_period_end' => true]);
                        ApiToken::query()->where('type', 'app')->whereIn('addon_install_id', $record->installs()->select('id'))->delete();
                        Notification::make()->title('Add-on suspended')->success()->send();
                    }),
                Action::make('restore')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Addon $record) => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(function (Addon $record) {
                        $record->update(['status' => $record->live_version_id ? 'published' : 'draft']);
                        Notification::make()->title('Add-on restored')->success()->send();
                    }),
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Addon $record) => $record->isListed() ? route('marketplace.show', $record) : null, shouldOpenInNewTab: true)
                    ->visible(fn (Addon $record) => $record->isListed()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddons::route('/'),
        ];
    }
}
