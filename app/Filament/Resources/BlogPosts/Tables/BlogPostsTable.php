<?php

namespace App\Filament\Resources\BlogPosts\Tables;

use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('featured_image_path')->label('')->disk('public')->height(40),
                TextColumn::make('title')->searchable()->wrap()->weight('medium'),
                TextColumn::make('wp_status')
                    ->label('Blog')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, BlogPost $record): string => $record->blogStatusLabel())
                    ->color(fn (?string $state): string => match ($state) {
                        'publish' => 'success',
                        'draft', 'pending', 'future' => 'warning',
                        default => 'gray',
                    })
                    ->default(''),
                TextColumn::make('synced_at')->label('Last sent')->since()->placeholder('—'),
                TextColumn::make('updated_at')->label('Edited')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('viewOnBlog')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (BlogPost $record): ?string => $record->wp_url)
                    ->openUrlInNewTab()
                    ->visible(fn (BlogPost $record): bool => filled($record->wp_url)),
                EditAction::make(),
            ]);
    }
}
