<?php

namespace App\Filament\Resources\SecurityFindings\Tables;

use App\Models\SecurityFinding;
use App\Services\Agents\SecurityFindingApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

class SecurityFindingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('detected_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('category')->badge(),
                TextColumn::make('severity')
                    ->badge()
                    ->colors([
                        'gray' => 'low',
                        'warning' => 'medium',
                        'danger' => fn ($state) => in_array($state, ['high', 'critical'], true),
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => fn ($state) => in_array($state, ['open', 'dismissed'], true),
                        'warning' => 'scheduled',
                        'success' => 'fixed',
                    ]),
                TextColumn::make('detected_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'scheduled' => 'Scheduled',
                    'fixed' => 'Fixed',
                    'dismissed' => 'Dismissed',
                ]),
                SelectFilter::make('severity')->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'critical' => 'Critical',
                ]),
            ])
            ->recordActions([
                Action::make('viewAnalysis')
                    ->label('View analysis')
                    ->modalHeading(fn (SecurityFinding $record) => $record->title)
                    ->modalContent(fn (SecurityFinding $record) => view('filament.security-findings.analysis', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('approveAndSchedule')
                    ->label('Approve fix')
                    ->color('success')
                    ->visible(fn (SecurityFinding $record) => auth()->user()->isSuperAdmin()
                        && $record->isFixable()
                        && $record->status === SecurityFinding::STATUS_OPEN)
                    ->modalDescription('Every fix is applied with a backup of what it changes, a health check of the website afterwards, and an automatic rollback if anything fails. Fixes never change customer data.')
                    ->form([
                        Radio::make('when')
                            ->label('When should the fix run?')
                            ->options([
                                'now' => 'Now (within a minute)',
                                'schedule' => 'At a time I choose',
                            ])
                            ->default('now')
                            ->live()
                            ->required(),
                        DateTimePicker::make('scheduled_at')
                            ->label('Apply the fix starting')
                            ->native(false)
                            ->minDate(now())
                            ->visible(fn (Get $get): bool => $get('when') === 'schedule')
                            ->required(fn (Get $get): bool => $get('when') === 'schedule'),
                        DateTimePicker::make('scheduled_until')
                            ->label('It may run until')
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('when') === 'schedule')
                            ->required(fn (Get $get): bool => $get('when') === 'schedule')
                            ->after('scheduled_at'),
                        Toggle::make('notify_users')
                            ->label('Tell users about the maintenance')
                            ->helperText('Off by default: most fixes cause no downtime. Turn it on for a fix that might briefly interrupt the site.')
                            ->default(false),
                    ])
                    ->action(function (SecurityFinding $record, array $data) {
                        $notify = (bool) ($data['notify_users'] ?? false);
                        $service = app(SecurityFindingApprovalService::class);

                        try {
                            $data['when'] === 'schedule'
                                ? $service->approveAndSchedule($record, auth()->user(), Carbon::parse($data['scheduled_at']), Carbon::parse($data['scheduled_until']), $notify)
                                : $service->approveAndRunNow($record, auth()->user(), $notify);
                        } catch (AuthorizationException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title($data['when'] === 'schedule' ? 'Fix scheduled' : 'Fix approved — it runs within a minute')
                            ->body($notify ? 'Users have been told about the maintenance.' : 'Users were not notified.')
                            ->success()
                            ->send();
                    }),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SecurityFinding $record) => $record->status === SecurityFinding::STATUS_OPEN)
                    ->action(fn (SecurityFinding $record) => $record->update(['status' => SecurityFinding::STATUS_DISMISSED])),
            ])
            ->toolbarActions([]);
    }
}
