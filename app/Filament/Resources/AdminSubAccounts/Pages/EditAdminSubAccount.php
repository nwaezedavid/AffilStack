<?php

namespace App\Filament\Resources\AdminSubAccounts\Pages;

use App\Filament\Resources\AdminSubAccounts\AdminSubAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminSubAccount extends EditRecord
{
    protected static string $resource = AdminSubAccountResource::class;

    /**
     * @var array<int, string>
     */
    protected array $departments = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['departments'] = collect($this->record->getAllPermissions())
            ->pluck('name')
            ->map(fn (string $name) => str($name)->after('department.')->toString())
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->departments = $data['departments'] ?? [];
        unset($data['departments']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions(
            collect($this->departments)->map(fn (string $department) => "department.{$department}")->all()
        );
    }
}
