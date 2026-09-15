<?php

namespace App\Filament\Resources\CreativeTasks\Tables;

use App\Models\AgentTask;
use App\Services\Agents\CreativeTaskApprovalService;
use App\Services\Agents\TonyAgentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class CreativeTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        TonyAgentService::KIND_SITE_PAGE => 'Static page',
                        TonyAgentService::KIND_HOMEPAGE_FEATURE => 'Homepage feature',
                        TonyAgentService::KIND_FAQ_ITEM => 'FAQ entry',
                        TonyAgentService::KIND_BRANDING => 'Branding',
                        default => $state,
                    }),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('summary')->label('Brief')->wrap()->limit(80),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => fn ($state) => in_array($state, ['pending', 'declined'], true),
                        'warning' => 'running',
                        'success' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['failed', 'rolled_back'], true),
                    ]),
                TextColumn::make('requestedBy.name')->label('Requested by')->placeholder('—'),
                TextColumn::make('created_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    TonyAgentService::KIND_SITE_PAGE => 'Static page',
                    TonyAgentService::KIND_HOMEPAGE_FEATURE => 'Homepage feature',
                    TonyAgentService::KIND_FAQ_ITEM => 'FAQ entry',
                    TonyAgentService::KIND_BRANDING => 'Branding',
                ]),
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'completed' => 'Completed',
                    'declined' => 'Declined',
                    'failed' => 'Failed',
                ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->color('gray')
                    ->url(fn (AgentTask $record) => route('creative-tasks.preview', $record))
                    ->openUrlInNewTab(),

                Action::make('approveAndPublish')
                    ->label('Approve & publish')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This publishes Tony\'s draft to the live site immediately.')
                    ->visible(fn (AgentTask $record) => $record->status === AgentTask::STATUS_PENDING && auth()->user()->isSuperAdmin())
                    ->action(function (AgentTask $record) {
                        try {
                            app(CreativeTaskApprovalService::class)->approveAndPublish($record, auth()->user());
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        $record->refresh();

                        Notification::make()
                            ->title($record->status === AgentTask::STATUS_COMPLETED ? 'Published.' : 'Publishing failed — see the task for details.')
                            ->status($record->status === AgentTask::STATUS_COMPLETED ? 'success' : 'danger')
                            ->body($record->result)
                            ->send();
                    }),

                Action::make('decline')
                    ->label('Decline')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')->label('Reason (optional)')->rows(2),
                    ])
                    ->visible(fn (AgentTask $record) => $record->status === AgentTask::STATUS_PENDING)
                    ->action(function (AgentTask $record, array $data) {
                        app(CreativeTaskApprovalService::class)->decline($record, auth()->user(), $data['reason'] ?? null);

                        Notification::make()->title('Draft declined.')->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
