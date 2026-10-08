<?php

namespace App\Filament\Resources\FeaturePages\Pages;

use App\Filament\Pages\FeaturesOverviewEditor;
use App\Filament\Resources\FeaturePages\FeaturePageResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFeaturePages extends ListRecords
{
    protected static string $resource = FeaturePageResource::class;

    protected ?string $subheading = 'One page per tool at /features/{address}, also listed in the header\'s Tools menu and on the /features overview. Drag to reorder.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('overview')
                ->label('Edit the /features overview page')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->url(FeaturesOverviewEditor::getUrl()),
            Action::make('viewOverview')
                ->label('View /features')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('features.index'))
                ->openUrlInNewTab(),
            CreateAction::make(),
        ];
    }
}
