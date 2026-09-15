<?php

namespace App\Filament\Resources\MarketingCampaigns\Pages;

use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use App\Models\Offer;
use App\Services\Agents\BrainAgentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

class ListMarketingCampaigns extends ListRecords
{
    protected static string $resource = MarketingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('draftCampaign')
                ->label('Draft new campaign')
                ->color('primary')
                ->form([
                    Select::make('offer_id')
                        ->label('Offer')
                        ->options(fn () => Offer::query()->orderBy('product_name')->pluck('product_name', 'id'))
                        ->searchable()
                        ->required(),
                    Textarea::make('goal')
                        ->label('Goal (optional)')
                        ->placeholder('e.g. maximize signups for under $2 per click')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        app(BrainAgentService::class)->draftCampaign(
                            Offer::query()->findOrFail($data['offer_id']),
                            auth()->user(),
                            $data['goal'] ?: null,
                        );
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Draft ready — review it below before approving.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
