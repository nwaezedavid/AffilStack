<?php

namespace App\Filament\Resources\AffiliateSwipes\Pages;

use App\Filament\Resources\AffiliateSwipes\AffiliateSwipeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateSwipe extends EditRecord
{
    protected static string $resource = AffiliateSwipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
