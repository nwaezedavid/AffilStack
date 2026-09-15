<?php

namespace App\Filament\Resources\AdminSubAccounts\Pages;

use App\Filament\Resources\AdminSubAccounts\AdminSubAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminSubAccount extends CreateRecord
{
    protected static string $resource = AdminSubAccountResource::class;

    /**
     * @var array<int, string>
     */
    protected array $departments = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // "departments" isn't a users-table column — it's translated into
        // Spatie permissions in afterCreate() below, once the record (and
        // therefore its permission pivot) exists.
        $this->departments = $data['departments'] ?? [];
        unset($data['departments']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles(['admin_sub']);
        $this->record->syncPermissions(
            collect($this->departments)->map(fn (string $department) => "department.{$department}")->all()
        );
    }
}
