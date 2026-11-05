<?php

namespace App\Filament\Resources\TrialInvites\Pages;

use App\Filament\Resources\TrialInvites\TrialInviteResource;
use App\Models\Plan;
use App\Services\Trials\TrialInviteService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * "Send trial invite" — creates the invite and emails the signed link
 * through TrialInviteService, which also enforces one trial per email.
 */
class CreateTrialInvite extends CreateRecord
{
    protected static string $resource = TrialInviteResource::class;

    protected static ?string $title = 'Send trial invite';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(TrialInviteService::class)->send(
                $data['name'],
                $data['email'],
                Plan::findOrFail($data['plan_id']),
                $data['billing_cycle'],
                auth()->user(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['data.email' => $e->getMessage()]);
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Send trial invite');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Trial invite sent');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
