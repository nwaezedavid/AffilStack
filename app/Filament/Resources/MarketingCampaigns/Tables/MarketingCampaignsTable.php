<?php

namespace App\Filament\Resources\MarketingCampaigns\Tables;

use App\Models\BrainAgentSetting;
use App\Models\MarketingCampaign;
use App\Services\Agents\BrainAgentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class MarketingCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('offer.product_name')->label('Offer')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => MarketingCampaign::STATUS_DRAFT,
                        'warning' => fn ($state) => in_array($state, [MarketingCampaign::STATUS_APPROVED, MarketingCampaign::STATUS_OPTIMIZING], true),
                        'success' => MarketingCampaign::STATUS_RUNNING,
                        'danger' => MarketingCampaign::STATUS_FAILED,
                    ]),
                TextColumn::make('createdBy.name')->label('Drafted by'),
                TextColumn::make('created_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    MarketingCampaign::STATUS_DRAFT => 'Draft',
                    MarketingCampaign::STATUS_APPROVED => 'Approved',
                    MarketingCampaign::STATUS_RUNNING => 'Running',
                    MarketingCampaign::STATUS_OPTIMIZING => 'Optimizing',
                    MarketingCampaign::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->recordActions([
                Action::make('viewBrief')
                    ->label('View brief')
                    ->modalHeading(fn (MarketingCampaign $record) => $record->title)
                    ->modalContent(fn (MarketingCampaign $record) => view('filament.marketing-campaigns.brief', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('approveAndLaunch')
                    ->label('Approve & launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This will use your connected Meta Ads MCP server to actually create and run this campaign.')
                    ->visible(fn (MarketingCampaign $record) => $record->isDraft()
                        && BrainAgentSetting::current()->canLaunchLiveCampaigns()
                        && auth()->user()->isSuperAdmin())
                    ->action(function (MarketingCampaign $record) {
                        try {
                            app(BrainAgentService::class)->approveAndLaunch($record, auth()->user());
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title($record->fresh()->status === MarketingCampaign::STATUS_RUNNING
                                ? 'Campaign launched — Brain will keep optimizing it automatically.'
                                : 'Launch failed — see the brief for details.')
                            ->status($record->fresh()->status === MarketingCampaign::STATUS_RUNNING ? 'success' : 'danger')
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
