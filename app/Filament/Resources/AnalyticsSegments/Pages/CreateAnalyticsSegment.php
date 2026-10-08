<?php

namespace App\Filament\Resources\AnalyticsSegments\Pages;

use App\Filament\Resources\AnalyticsSegments\AnalyticsSegmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnalyticsSegment extends CreateRecord
{
    protected static string $resource = AnalyticsSegmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge($data, ['created_by' => auth()->id(), 'is_preset' => false]);
    }
}
