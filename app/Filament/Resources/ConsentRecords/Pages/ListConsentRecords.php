<?php

namespace App\Filament\Resources\ConsentRecords\Pages;

use App\Filament\Resources\ConsentRecords\ConsentRecordResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListConsentRecords extends ListRecords
{
    protected static string $resource = ConsentRecordResource::class;

    protected ?string $subheading = 'Proof of consent: every cookie choice made on the site, with the policy version it was made against. Records hold an anonymous consent id and a salted hash of the IP — no raw IP addresses.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download CSV (last 12 months)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => (bool) auth()->user()?->isFullAdmin())
                ->url(fn (): string => route('admin.analytics.export', ['type' => 'consent', 'from' => now()->subMonths(12)->toDateString(), 'to' => now()->toDateString()])),
        ];
    }
}
