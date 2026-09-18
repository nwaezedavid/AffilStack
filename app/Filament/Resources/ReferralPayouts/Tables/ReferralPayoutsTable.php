<?php

namespace App\Filament\Resources\ReferralPayouts\Tables;

use App\Models\ReferralPayout;
use App\Services\Referrals\PayoutDisbursementService;
use App\Services\Referrals\ReferralPayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReferralPayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->description(fn (ReferralPayout $record) => $record->user->email),
                TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, ReferralPayout $record) => '$'.number_format($state / 100, 2).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('payout_method')
                    ->label('Method')
                    ->formatStateUsing(fn ($state) => config("referrals.payout_methods.{$state}", $state)),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'requested',
                        'success' => 'paid',
                        'danger' => 'rejected',
                    ]),
                TextColumn::make('reference')->placeholder('—'),
                TextColumn::make('requested_at')->dateTime('M j, Y g:ia')->sortable(),
                TextColumn::make('processed_at')->dateTime('M j, Y g:ia')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'requested' => 'Requested',
                    'paid' => 'Paid',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([
                Action::make('viewPayoutDetails')
                    ->label('View payout details')
                    ->color('gray')
                    ->modalHeading('Where to send this payout')
                    ->modalContent(fn (ReferralPayout $record) => view('filament.referral-payouts.details', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('disburseFromWallet')
                    ->label('Disburse from wallet')
                    ->color('primary')
                    ->icon(Heroicon::OutlinedBolt)
                    ->requiresConfirmation()
                    ->modalDescription('Sends this payout automatically via Flutterwave (bank_transfer, NGN) or PayPal (paypal), drawing from the payout wallet balance.')
                    ->visible(fn (ReferralPayout $record) => $record->isRequested() && app(PayoutDisbursementService::class)->canAutoDisburse($record))
                    ->action(function (ReferralPayout $record) {
                        try {
                            app(PayoutDisbursementService::class)->disburse($record, auth()->user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Payout disbursed from the wallet — the affiliate has been notified.')
                            ->success()
                            ->send();
                    }),

                Action::make('markPaid')
                    ->label('Mark paid')
                    ->color('success')
                    ->visible(fn (ReferralPayout $record) => $record->isRequested())
                    ->form([
                        TextInput::make('reference')
                            ->label('Payment reference')
                            ->helperText('A PayPal transaction ID, bank reference, or similar proof the money was sent.')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('note')
                            ->label('Note (optional)')
                            ->rows(2),
                    ])
                    ->action(function (ReferralPayout $record, array $data) {
                        try {
                            app(ReferralPayoutService::class)->processPayout(
                                $record,
                                auth()->user(),
                                $data['reference'],
                                Str::of($data['note'] ?? '')->trim()->value() ?: null,
                            );
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Payout marked paid — the affiliate has been notified.')
                            ->success()
                            ->send();
                    }),

                Action::make('rejectPayout')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The commissions in this batch will be released back to "approved" so the affiliate can request them again.')
                    ->visible(fn (ReferralPayout $record) => $record->isRequested())
                    ->form([
                        Textarea::make('note')
                            ->label('Reason')
                            ->helperText('Shown to the affiliate — explain what needs to change.')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (ReferralPayout $record, array $data) {
                        try {
                            app(ReferralPayoutService::class)->rejectPayout($record, auth()->user(), $data['note']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Payout rejected — the affiliate has been notified.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
