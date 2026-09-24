<?php

namespace App\Filament\Resources\NotFoundLogs\Pages;

use App\Filament\Resources\NotFoundLogs\Actions\CreateRedirectAction;
use App\Filament\Resources\NotFoundLogs\NotFoundLogResource;
use App\Models\Redirect;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The dedicated per-404 detail page requested alongside the rest of the
 * statistics work ("when expanded, it should have its own page where I can
 * see details of each 404 error and also be able to redirect the link to
 * any page I prefer") — the list's own quick "Create redirect" action
 * (NotFoundLogsTable) still exists for triaging many at once, this is the
 * single-record view for looking at one closely first.
 */
class ViewNotFoundLog extends ViewRecord
{
    protected static string $resource = NotFoundLogResource::class;

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('This 404')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('path')->label('Path')->prefix('/')->columnSpanFull(),
                        TextEntry::make('hits_count')->label('Total hits'),
                        TextEntry::make('referer')->label('Last referer')->placeholder('None recorded'),
                        TextEntry::make('first_seen_at')->label('First seen')->dateTime(),
                        TextEntry::make('last_seen_at')->label('Last seen')->dateTime(),
                    ]),
                Section::make('Existing redirect')
                    ->visible(fn () => Redirect::where('from_path', $this->record->path)->exists())
                    ->schema(function () {
                        $redirect = Redirect::where('from_path', $this->record->path)->first();

                        return [
                            TextEntry::make('redirect_to')
                                ->label('Currently redirects to')
                                ->state($redirect?->to_path)
                                ->badge()
                                ->color('success'),
                            TextEntry::make('redirect_status')
                                ->label('Status code')
                                ->state($redirect?->status_code),
                            TextEntry::make('redirect_hits')
                                ->label('Times this redirect fired')
                                ->state($redirect?->hits_count ?? 0),
                        ];
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateRedirectAction::make(),
            DeleteAction::make(),
        ];
    }
}
