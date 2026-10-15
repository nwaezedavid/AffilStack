<?php

namespace App\Filament\Resources\ReferralPayouts\Tables;

use App\Models\ReferralPayout;
use App\Services\Referrals\PayoutDisbursementService;
use App\Services\Referrals\PayoutMethodService;
use App\Services\Referrals\ReferralPayoutService;
use App\Support\ReferralProgram;
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
                    ->formatStateUsing(fn ($state, ReferralPayout $record) => ReferralProgram::money((int) $state, $record->currency))
                    ->description(fn (ReferralPayout $record) => $record->sent_amount_cents && $record->sent_currency !== $record->currency ? ReferralProgram::money((int) $record->sent_amount_cents, (string) $record->sent_currency).' sent' : null)
                    ->sortable(),
                TextColumn::make('payout_method')
                    ->label('Method')
                    ->formatStateUsing(fn ($state) => ReferralProgram::methodLabel($state))
                    ->description(fn (ReferralPayout $record) => PayoutMethodService::summary($record->payout_method, $record->payout_details)),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'requested',
                        'info' => 'disbursing',
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
                    'disbursing' => 'Disbursing (confirm in gateway)',
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
                    ->label(fn (ReferralPayout $record) => $record->payout_method === 'paystack_bank' ? 'Pay with Paystack' : 'Disburse from wallet')
                    ->color('primary')
                    ->icon(Heroicon::OutlinedBolt)
                    ->requiresConfirmation()
                    ->modalHeading(fn (ReferralPayout $record) => $record->payout_method === 'paystack_bank' ? 'Send this payout with Paystack?' : 'Disburse this payout?')
                    ->modalDescription(fn (ReferralPayout $record) => $record->payout_method === 'paystack_bank'
                        ? 'Sends '.ReferralProgram::money(app(PayoutDisbursementService::class)->paystackAmountKobo($record), 'NGN')
                            .($record->currency === 'USD' ? ' ('.ReferralProgram::money($record->amount_cents).' at ₦'.number_format(ReferralProgram::ngnPerUsd(), 2).' per $1)' : '')
                            .' from your Paystack balance to '.PayoutMethodService::summary($record->payout_method, $record->payout_details)
                            .'. The partner is emailed once Paystack confirms the transfer.'
                        : 'Sends this payout automatically via Flutterwave (bank_transfer, NGN) or PayPal (paypal), drawing from the payout wallet balance.')
                    ->visible(fn (ReferralPayout $record) => $record->isRequested() && app(PayoutDisbursementService::class)->canAutoDisburse($record))
                    ->action(function (ReferralPayout $record) {
                        try {
                            app(PayoutDisbursementService::class)->disburse($record, auth()->user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        $record->refresh();

                        Notification::make()
                            ->title($record->status === 'paid'
                                ? 'Payout sent — the partner has been notified.'
                                : 'Transfer queued with Paystack. It will be marked paid automatically when Paystack confirms it.')
                            ->success()
                            ->send();
                    }),

                Action::make('markPaid')
                    ->label('Mark paid')
                    ->color('success')
                    // Also offered on "disbursing" — a transfer whose outcome
                    // was unclear, once confirmed in the gateway dashboard.
                    ->visible(fn (ReferralPayout $record) => in_array($record->status, ['requested', 'disbursing'], true))
                    ->form([
                        TextInput::make('reference')
                            ->label(fn (ReferralPayout $record) => $record->payout_method === 'usdt' ? 'Transaction hash' : 'Payment reference')
                            ->helperText(fn (ReferralPayout $record) => $record->payout_method === 'usdt'
                                ? 'Paste the hash of the USDT transfer ('.(config('referrals.usdt_networks.'.($record->payout_details['network'] ?? '').'.label') ?? 'network').'). The partner sees it with a link to the block explorer.'
                                : 'A PayPal transaction ID, bank reference, or similar proof the money was sent.')
                            ->required()
                            ->maxLength(255)
                            ->rule(fn (ReferralPayout $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                                $pattern = $record->payout_method === 'usdt' ? config('referrals.usdt_networks.'.($record->payout_details['network'] ?? '').'.hash') : null;

                                if ($pattern && ! preg_match($pattern, trim((string) $value))) {
                                    $fail(($record->payout_details['network'] ?? '') === 'bep20'
                                        ? 'A BEP-20 transaction hash starts with 0x followed by 64 characters.'
                                        : 'A TRC-20 transaction hash is 64 letters and numbers.');
                                }
                            }),
                        Textarea::make('note')
                            ->label('Note (optional)')
                            ->rows(2),
                    ])
                    ->action(function (ReferralPayout $record, array $data) {
                        try {
                            app(ReferralPayoutService::class)->processPayout(
                                $record,
                                auth()->user(),
                                trim($data['reference']),
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
