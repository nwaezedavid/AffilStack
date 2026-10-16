<?php

namespace App\Filament\Resources\DeveloperEarnings;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\DeveloperEarnings\Pages\ListDeveloperEarnings;
use App\Models\DeveloperEarning;
use App\Support\ReferralProgram;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Feature 7: developers' share of each add-on payment. Earnings become
 * available after the refund window and are paid with Referral Payouts
 * (shown there as "Add-on earnings").
 */
class DeveloperEarningResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'marketplace';

    protected static ?string $model = DeveloperEarning::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Developer earnings';

    protected static ?string $modelLabel = 'developer earning';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
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
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('developer.display_name')->label('Developer')->searchable(),
                TextColumn::make('addon.slug')->label('Add-on'),
                TextColumn::make('type')->badge()->color(fn (string $state) => $state === 'sale' ? 'success' : 'danger'),
                TextColumn::make('gross_cents')->label('Payment')->formatStateUsing(fn ($state, DeveloperEarning $record) => ReferralProgram::money((int) $state, $record->currency)),
                TextColumn::make('amount_cents')->label('Developer share')->formatStateUsing(fn ($state, DeveloperEarning $record) => ReferralProgram::money((int) $state, $record->currency).' ('.rtrim(rtrim((string) $record->share_percent, '0'), '.').'%)'),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'paid' => 'info',
                    'pending' => 'warning',
                    default => 'danger',
                }),
                TextColumn::make('available_at')->date()->label('Available from'),
                TextColumn::make('occurred_at')->dateTime()->label('Date')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'In refund window', 'approved' => 'Available', 'paid' => 'Paid', 'rejected' => 'Rejected', 'reversed' => 'Reversed']),
            ])
            ->recordActions([
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The developer won\'t be paid this share — for example after fraud or a payment problem.')
                    ->visible(fn (DeveloperEarning $record) => in_array($record->status, ['pending', 'approved'], true) && $record->referral_payout_id === null)
                    ->action(function (DeveloperEarning $record) {
                        $record->update(['status' => 'rejected']);
                        Notification::make()->title('Earning rejected')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeveloperEarnings::route('/'),
        ];
    }
}
