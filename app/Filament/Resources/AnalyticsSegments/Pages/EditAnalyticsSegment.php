<?php

namespace App\Filament\Resources\AnalyticsSegments\Pages;

use App\Filament\Resources\AnalyticsSegments\AnalyticsSegmentResource;
use App\Models\AnalyticsSegment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnalyticsSegment extends EditRecord
{
    protected static string $resource = AnalyticsSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (AnalyticsSegment $record) => ! $record->is_preset),
        ];
    }
}
