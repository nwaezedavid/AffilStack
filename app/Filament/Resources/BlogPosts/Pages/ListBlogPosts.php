<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Pages\BlogSettings;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBlogPosts extends ListRecords
{
    protected static string $resource = BlogPostResource::class;

    protected ?string $subheading = 'Write or AI-draft posts here, then send them to the WordPress blog as a draft or publish them straight away.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Blog settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(BlogSettings::getUrl()),
            CreateAction::make()->label('New post'),
        ];
    }
}
