<?php

namespace App\Filament\Resources\FeaturePages\Pages;

use App\Filament\Resources\FeaturePages\FeaturePageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditFeaturePage extends EditRecord
{
    protected static string $resource = FeaturePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View page on the site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => $this->getRecord()->url().'?preview='.now()->timestamp)
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
