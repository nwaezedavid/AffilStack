<?php

namespace App\Filament\Resources\PartnerAnnouncements\Tables;

use App\Models\PartnerAnnouncement;
use App\Models\User;
use App\Notifications\PartnerAnnouncementPublished;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Notification as Notifier;

class PartnerAnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('state')
                    ->label('Status')
                    ->state(fn (PartnerAnnouncement $record) => match (true) {
                        $record->published_at === null => 'Draft',
                        $record->published_at->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('is_pinned')->label('Pinned')->boolean(),
                TextColumn::make('published_at')->label('Published')->dateTime('M j, Y g:ia')->placeholder('—')->sortable(),
                TextColumn::make('emailed_at')->label('Emailed')->dateTime('M j, Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('emailPartners')
                    ->label('Email partners')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (PartnerAnnouncement $record) => $record->isPublished())
                    ->requiresConfirmation()
                    ->modalHeading('Email this to every partner?')
                    ->modalDescription(fn (PartnerAnnouncement $record) => $record->emailed_at
                        ? 'This was already emailed on '.$record->emailed_at->format('M j, Y').'. Sending again emails everyone a second time.'
                        : 'Every active, approved partner gets this announcement by email, with a link to the Partner Portal.')
                    ->action(function (PartnerAnnouncement $record) {
                        $partners = User::query()->where('is_affiliate_only', true)->where('is_suspended', false)->get();

                        Notifier::send($partners, new PartnerAnnouncementPublished($record));
                        $record->update(['emailed_at' => now()]);

                        Notification::make()->title('Queued for '.$partners->count().' partners.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
