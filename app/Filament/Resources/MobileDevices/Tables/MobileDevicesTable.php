<?php

namespace App\Filament\Resources\MobileDevices\Tables;

use App\Models\MobileDevice;
use App\Services\Mobile\Push\MobilePush;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MobileDevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn (MobileDevice $record): ?string => $record->user?->email),
                TextColumn::make('name')
                    ->label('Phone')
                    ->placeholder('Unnamed')
                    ->description(fn (MobileDevice $record): string => trim(ucfirst((string) $record->platform).' '.($record->os_version ?? ''))),
                TextColumn::make('app_version')->label('App')->placeholder('—'),
                IconColumn::make('push_token_hash')
                    ->label('Notifications')
                    ->boolean()
                    ->getStateUsing(fn (MobileDevice $record): bool => $record->push_token_hash !== null),
                TextColumn::make('last_seen_at')->label('Last used')->since()->sortable(),
                TextColumn::make('authenticated_at')->label('Signed in')->dateTime()->sortable(),
                TextColumn::make('revoked_at')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (MobileDevice $record): string => $record->isActive() ? 'Signed in' : 'Signed out')
                    ->color(fn (string $state): string => $state === 'Signed in' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('platform')->options(['ios' => 'iPhone', 'android' => 'Android']),
                TernaryFilter::make('active')
                    ->label('Signed in')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->whereNotNull('revoked_at'),
                    ),
            ])
            ->recordActions([
                Action::make('sendTest')
                    ->label('Test notification')
                    ->icon('heroicon-o-bell')
                    ->visible(fn (MobileDevice $record): bool => $record->isActive() && $record->push_token_hash !== null)
                    ->action(function (MobileDevice $record): void {
                        $result = app(MobilePush::class)->sendTest($record);

                        Notification::make()
                            ->title($result['success'] ? 'Test notification sent' : 'Not sent')
                            ->body($result['message'])
                            ->status($result['success'] ? 'success' : 'danger')
                            ->send();
                    }),
                Action::make('signOut')
                    ->label('Sign out')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The app on this phone is signed out straight away and stops receiving notifications.')
                    ->visible(fn (MobileDevice $record): bool => $record->isActive())
                    ->action(function (MobileDevice $record): void {
                        $record->revoke();

                        Notification::make()->title('Phone signed out')->success()->send();
                    }),
            ]);
    }
}
