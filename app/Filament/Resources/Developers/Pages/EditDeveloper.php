<?php

namespace App\Filament\Resources\Developers\Pages;

use App\Filament\Resources\Developers\DeveloperResource;
use App\Models\ApiToken;
use Filament\Resources\Pages\EditRecord;

class EditDeveloper extends EditRecord
{
    protected static string $resource = DeveloperResource::class;

    /**
     * Suspending a developer switches their apps off for every customer.
     */
    protected function afterSave(): void
    {
        if ($this->record->status === 'suspended') {
            ApiToken::query()->where('type', 'app')
                ->whereHas('install.addon', fn ($query) => $query->where('developer_profile_id', $this->record->id))
                ->delete();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
