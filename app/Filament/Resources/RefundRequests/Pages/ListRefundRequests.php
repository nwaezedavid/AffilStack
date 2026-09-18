<?php

namespace App\Filament\Resources\RefundRequests\Pages;

use App\Filament\Resources\RefundRequests\RefundRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRefundRequests extends ListRecords
{
    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
