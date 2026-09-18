<?php

namespace App\Filament\Resources\NotFoundLogs\Tables;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotFoundLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('hits_count', 'desc')
            ->columns([
                TextColumn::make('path')->label('Path')->prefix('/')->searchable(),
                TextColumn::make('hits_count')->label('Hits')->numeric()->sortable(),
                TextColumn::make('referer')->label('Referer')->limit(40)->placeholder('—'),
                TextColumn::make('first_seen_at')->label('First seen')->dateTime()->since(),
                TextColumn::make('last_seen_at')->label('Last seen')->dateTime()->since()->sortable(),
            ])
            ->recordActions([
                Action::make('createRedirect')
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
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
