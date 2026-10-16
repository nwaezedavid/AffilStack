<?php

namespace App\Filament\Resources\AddonReviews\Pages;

use App\Filament\Resources\AddonReviews\AddonReviewResource;
use App\Models\AddonVersion;
use App\Services\Marketplace\AddonReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewAddonReview extends ViewRecord
{
    protected static string $resource = AddonReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve and publish')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (AddonVersion $record) => $record->status === 'submitted')
                ->schema([
                    Textarea::make('note')->label('Note to the developer (optional)')->rows(3)->maxLength(2000),
                ])
                ->action(function (AddonVersion $record, array $data) {
                    try {
                        app(AddonReviewService::class)->approve($record, auth()->user(), $data['note'] ?? null);
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Approved — it is live in the marketplace')->success()->send();
                    $this->redirect(AddonReviewResource::getUrl('index'));
                }),
            Action::make('reject')
                ->label('Send back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (AddonVersion $record) => $record->status === 'submitted')
                ->schema([
                    Textarea::make('note')->label('What needs to change')->helperText('The developer sees this exactly as written.')->required()->rows(5)->maxLength(4000),
                ])
                ->action(function (AddonVersion $record, array $data) {
                    try {
                        app(AddonReviewService::class)->reject($record, auth()->user(), $data['note']);
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Sent back to the developer')->success()->send();
                    $this->redirect(AddonReviewResource::getUrl('index'));
                }),
        ];
    }
}
