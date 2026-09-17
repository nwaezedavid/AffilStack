<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListApiTokens extends ListRecords
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAdminToken')
                ->label('Generate admin token')
                ->color('danger')
                ->visible(fn () => auth()->user()?->isFullAdmin() ?? false)
                ->modalDescription('Unlocks /api/v1/admin/* — users, plans, referral payouts, revenue. Only ever for your own automation; anyone using this token can read platform-wide business data.')
                ->form([
                    TextInput::make('name')
                        ->label('Name')
                        ->placeholder('e.g. Internal reporting script')
                        ->required(),
                ])
                ->action(function (array $data) {
                    // Belt and suspenders: EnsureAdminApiToken already re-checks
                    // isFullAdmin() live on every request, so this can never
                    // actually grant elevated access to anyone it's minted
                    // for by mistake — but there's no reason to let a
                    // non-full-admin create a misleadingly-labeled row here
                    // in the first place.
                    abort_unless(auth()->user()->isFullAdmin(), 403);

                    $result = ApiToken::generate(auth()->user(), $data['name'], 'admin');

                    Notification::make()
                        ->title('Admin token created')
                        ->body("Copy it now, it won't be shown again:\n{$result['plainText']}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
