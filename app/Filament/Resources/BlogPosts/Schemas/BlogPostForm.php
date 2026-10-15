<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Filament\Support\WebpFileUpload;
use App\Models\BlogPost;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->components([
                Section::make('Post')
                    ->columnSpan(['lg' => 2])
                    ->components([
                        TextInput::make('title')->required()->maxLength(200),
                        RichEditor::make('content')
                            ->label('Article')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'blockquote'],
                                ['undo', 'redo'],
                            ])
                            ->columnSpanFull(),
                        Textarea::make('excerpt')->label('Short summary')->rows(3)->maxLength(400)
                            ->helperText('Shown on the blog\'s post list and on the AffilStack homepage.'),
                    ]),
                Grid::make(1)->columnSpan(['lg' => 1])->components([
                    Section::make('On the blog')
                        ->components([
                            Text::make(fn (?BlogPost $record): string => $record?->blogStatusLabel() ?? 'Not sent yet'),
                            Text::make(fn (?BlogPost $record): string => $record?->last_error ? 'Last attempt failed: '.$record->last_error : '')
                                ->color('danger')
                                ->visible(fn (?BlogPost $record): bool => filled($record?->last_error)),
                        ]),
                    Section::make('Featured image')
                        ->components([
                            WebpFileUpload::make('featured_image_path')
                                ->hiddenLabel()
                                ->image()
                                ->disk('public')
                                ->directory('blog-posts')
                                ->helperText('1200×630 or larger works best.'),
                        ]),
                    Section::make('Categories & tags')
                        ->components([
                            TagsInput::make('categories')->placeholder('Add a category')->helperText('Created on the blog if they don\'t exist yet.'),
                            TagsInput::make('tags')->placeholder('Add a tag'),
                        ]),
                    Section::make('SEO (Rank Math)')
                        ->collapsible()
                        ->components([
                            TextInput::make('focus_keyword')->maxLength(120),
                            TextInput::make('seo_title')->label('SEO title')->maxLength(70)->helperText('Up to about 60 characters.'),
                            Textarea::make('seo_description')->label('Meta description')->rows(3)->maxLength(170)->helperText('Up to about 155 characters.'),
                            TextInput::make('slug')->label('Address (slug)')->maxLength(120)->helperText('Leave blank to use the title.'),
                        ]),
                ]),
            ]),
        ]);
    }
}
