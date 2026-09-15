<?php

namespace App\Filament\Resources\CreativeTasks\Pages;

use App\Filament\Resources\CreativeTasks\CreativeTaskResource;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Services\Agents\TonyAgentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Throwable;

class ListCreativeTasks extends ListRecords
{
    protected static string $resource = CreativeTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestDraft')
                ->label('Request new content')
                ->color('primary')
                ->form([
                    Select::make('kind')
                        ->label('What should Tony draft?')
                        ->options([
                            TonyAgentService::KIND_SITE_PAGE => 'Static page',
                            TonyAgentService::KIND_HOMEPAGE_FEATURE => 'Homepage feature card',
                            TonyAgentService::KIND_FAQ_ITEM => 'FAQ entry',
                            TonyAgentService::KIND_BRANDING => 'Branding & site copy',
                        ])
                        ->required()
                        ->live(),
                    Select::make('target_id')
                        ->label('Existing item to revise')
                        ->helperText('Leave blank to have Tony create a brand-new one instead.')
                        ->options(fn (Get $get) => match ($get('kind')) {
                            TonyAgentService::KIND_SITE_PAGE => SitePage::query()->pluck('title', 'id'),
                            TonyAgentService::KIND_HOMEPAGE_FEATURE => HomepageFeature::query()->pluck('title', 'id'),
                            TonyAgentService::KIND_FAQ_ITEM => FaqItem::query()->pluck('question', 'id'),
                            default => [],
                        })
                        ->searchable()
                        ->visible(fn (Get $get) => in_array($get('kind'), [
                            TonyAgentService::KIND_SITE_PAGE,
                            TonyAgentService::KIND_HOMEPAGE_FEATURE,
                            TonyAgentService::KIND_FAQ_ITEM,
                        ], true)),
                    Textarea::make('brief')
                        ->label('What should it say / change?')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    try {
                        app(TonyAgentService::class)->draft(
                            $data['kind'],
                            $data['brief'],
                            auth()->user(),
                            $data['target_id'] ?: null,
                        );
                    } catch (Throwable $e) {
                        Notification::make()->title("Tony couldn't draft that.")->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Draft ready — preview it below before approving.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
