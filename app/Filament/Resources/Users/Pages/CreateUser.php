<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\AssignsComplimentaryPlan;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use AssignsComplimentaryPlan;

    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        if (! $user->roles()->exists()) {
            $user->assignRole('user');
        }

        $this->assignComplimentaryPlan($user);
    }
}
