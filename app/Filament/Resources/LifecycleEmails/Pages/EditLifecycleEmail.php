<?php

namespace App\Filament\Resources\LifecycleEmails\Pages;

use App\Filament\Resources\LifecycleEmails\LifecycleEmailResource;
use App\Filament\Resources\LifecycleEmails\Schemas\LifecycleEmailForm;
use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Services\Lifecycle\LifecycleEmailPreview;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EditLifecycleEmail extends EditRecord
{
    protected static string $resource = LifecycleEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test to me')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->action(function (): void {
                    $admin = auth()->user();
                    $email = LifecycleEmailPreview::fromState($this->data ?? [], $this->getRecord());

                    try {
                        Mail::to($admin)->send(new LifecycleMail($email, $admin, ['bonus_credits' => (int) $email->bonus_credits]));
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->title('The test email could not be sent')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Test sent to '.$admin->email)
                        ->body('It uses what\'s on screen right now, even unsaved changes.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (LifecycleEmail $record) => str_starts_with($record->key, 'custom.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if ($record->type === LifecycleEmail::TYPE_FESTIVE) {
            $data['date_rule'] = LifecycleEmailForm::normalizeDateRule($data['date_rule'] ?? null, $record->date_rule);
        }

        return $data;
    }
}
