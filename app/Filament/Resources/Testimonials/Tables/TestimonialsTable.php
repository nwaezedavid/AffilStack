<?php

namespace App\Filament\Resources\Testimonials\Tables;

use App\Models\Testimonial;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('avatar_path')->disk('public')->circular()->label('Photo'),
                TextColumn::make('author_name')->searchable(),
                TextColumn::make('author_role')->placeholder('—'),
                TextColumn::make('user.email')
                    ->label('Submitted by')
                    ->placeholder('Admin (direct entry)')
                    ->toggleable(),
                TextColumn::make('quote')->limit(60)->wrap(),
                TextColumn::make('rating')->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state).str_repeat('☆', 5 - $state) : '—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Testimonial::STATUS_PENDING => 'warning',
                        Testimonial::STATUS_APPROVED => 'success',
                        Testimonial::STATUS_DECLINED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Testimonial::STATUS_PENDING => 'Pending review',
                    Testimonial::STATUS_APPROVED => 'Approved',
                    Testimonial::STATUS_DECLINED => 'Declined',
                ]),
            ])
            ->recordActions([
                // "review, edit and approve it. Once approved, it will
                // become ready to be displayed in the frontend" — one click
                // sets both status and is_published together, so approving
                // a customer's submission is exactly one decision, not two
                // fields to remember to reconcile.
                Action::make('approve')
                    ->visible(fn (Testimonial $record) => $record->isPending())
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Testimonial $record) {
                        $record->update(['status' => Testimonial::STATUS_APPROVED, 'is_published' => true]);
                        Notification::make()->title('Testimonial approved')->success()->send();
                    }),
                Action::make('decline')
                    ->visible(fn (Testimonial $record) => $record->isPending())
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This never shows on the homepage. The customer isn\'t notified — edit their quote first if you\'d rather fix it up and approve it instead.')
                    ->action(function (Testimonial $record) {
                        $record->update(['status' => Testimonial::STATUS_DECLINED, 'is_published' => false]);
                        Notification::make()->title('Testimonial declined')->send();
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
