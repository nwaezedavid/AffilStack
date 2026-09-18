<?php

namespace App\Filament\Resources\AffiliateApplications\Tables;

use App\Models\AffiliateApplication;
use App\Services\Referrals\AffiliateApplicationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

class AffiliateApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('applied_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (AffiliateApplication $record) => $record->email),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('promotion_channels')
                    ->label('How they\'ll promote')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                TextColumn::make('applied_at')->dateTime('M j, Y g:ia')->sortable(),
                TextColumn::make('reviewed_at')->dateTime('M j, Y g:ia')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([
                Action::make('viewMessage')
                    ->label('View application details')
                    ->color('gray')
                    ->modalHeading('Application details')
                    ->modalContent(fn (AffiliateApplication $record) => view('filament.affiliate-applications.message', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Creates their affiliate account and emails them a link to set a password.')
                    ->visible(fn (AffiliateApplication $record) => $record->isPending())
                    ->action(function (AffiliateApplication $record) {
                        try {
                            app(AffiliateApplicationService::class)->approve($record, auth()->user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Application approved — the applicant has been emailed a link to set their password.')
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateApplication $record) => $record->isPending())
                    ->form([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->helperText('Shown to the applicant by email.')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (AffiliateApplication $record, array $data) {
                        try {
                            app(AffiliateApplicationService::class)->reject($record, auth()->user(), $data['reason']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Application rejected — the applicant has been notified.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
