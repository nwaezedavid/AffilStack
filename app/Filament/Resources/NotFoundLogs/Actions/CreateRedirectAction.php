<?php

namespace App\Filament\Resources\NotFoundLogs\Actions;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Shared between the 404 Monitor's list table (a quick action for someone
 * triaging a whole page of hits) and its detail page (ViewNotFoundLog —
 * the "expand a 404 for its own page, then redirect it" flow requested
 * alongside the rest of the statistics work). Same cycle-check and
 * updateOrCreate-by-from_path behaviour either way.
 */
class CreateRedirectAction
{
    public static function make(): Action
    {
        return Action::make('createRedirect')
            ->label('Create redirect')
            ->icon('heroicon-o-arrows-right-left')
            ->schema([
                TextInput::make('to_path')
                    ->label('Redirect to')
                    ->required()
                    ->maxLength(255)
                    ->helperText('A relative path (e.g. "/pricing") or a full URL.'),
                Select::make('status_code')
                    ->label('Redirect type')
                    ->options([301 => '301 — Permanent', 302 => '302 — Temporary'])
                    ->default(301)
                    ->required(),
            ])
            ->action(function (NotFoundLog $record, array $data): void {
                $existingId = Redirect::where('from_path', $record->path)->value('id');

                if (Redirect::wouldCreateCycle($record->path, $data['to_path'], $existingId)) {
                    Notification::make()
                        ->title('Could not create redirect')
                        ->body('This would send visitors in a redirect loop — check where this path (or one further down the chain) already redirects to.')
                        ->danger()
                        ->send();

                    return;
                }

                Redirect::updateOrCreate(
                    ['from_path' => $record->path],
                    ['to_path' => $data['to_path'], 'status_code' => $data['status_code']]
                );

                Notification::make()
                    ->title("Redirect created for /{$record->path}")
                    ->success()
                    ->send();
            });
    }
}
