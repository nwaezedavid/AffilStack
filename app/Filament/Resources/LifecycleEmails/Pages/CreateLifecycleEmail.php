<?php

namespace App\Filament\Resources\LifecycleEmails\Pages;

use App\Filament\Resources\LifecycleEmails\LifecycleEmailResource;
use App\Filament\Resources\LifecycleEmails\Schemas\LifecycleEmailForm;
use App\Models\LifecycleEmail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateLifecycleEmail extends CreateRecord
{
    protected static string $resource = LifecycleEmailResource::class;

    /**
     * Custom campaigns get a "custom." key so they're never mistaken for
     * (or overwritten by) a preset.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = 'custom.'.Str::slug($data['name'] ?? 'email', '_');
        $key = $base;
        $suffix = 2;

        while (LifecycleEmail::where('key', $key)->exists()) {
            $key = $base.'_'.$suffix++;
        }

        $data['key'] = $key;
        $data['date_rule'] = ($data['type'] ?? null) === LifecycleEmail::TYPE_FESTIVE
            ? LifecycleEmailForm::normalizeDateRule($data['date_rule'] ?? null, null)
            : null;
        $data['sort_order'] = (int) LifecycleEmail::max('sort_order') + 1;

        return $data;
    }
}
