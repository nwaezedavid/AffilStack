<?php

namespace App\Filament\Resources\SecurityFindings\Tables;

use App\Models\SecurityFinding;
use App\Services\Agents\SecurityFindingApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
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
                    ->label('Approve & schedule fix')
                    ->color('success')
                    ->visible(fn (SecurityFinding $record) => auth()->user()->isSuperAdmin()
                        && $record->isFixable()
                        && $record->status === SecurityFinding::STATUS_OPEN)
                    ->form([
                        DateTimePicker::make('scheduled_at')
                            ->label('Apply the fix starting')
                            ->native(false)
                            ->minDate(now())
                            ->required(),
                        DateTimePicker::make('scheduled_until')
                            ->label('Users will be told it may run until')
                            ->native(false)
                            ->required()
                            ->after('scheduled_at'),
                    ])
                    ->action(function (SecurityFinding $record, array $data) {
                        try {
                            app(SecurityFindingApprovalService::class)->approveAndSchedule(
                                $record,
                                auth()->user(),
                                Carbon::parse($data['scheduled_at']),
                                Carbon::parse($data['scheduled_until']),
                            );
                        } catch (AuthorizationException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Fix scheduled — every user has been notified of the maintenance window.')
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
