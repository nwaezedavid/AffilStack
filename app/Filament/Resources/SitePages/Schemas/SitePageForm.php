<?php

namespace App\Filament\Resources\SitePages\Schemas;

use App\Filament\Support\WebpFileUpload;
use App\Services\AI\AIGenerationException;
use App\Services\Seo\SeoMetaAssistant;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SitePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get, $operation) => $operation === 'create'
                        ? $set('slug', Str::slug($state))
                        : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)
                    ->helperText('The page URL, e.g. "about" for /about. Changing this breaks any existing links to the page.'),
                RichEditor::make('content')->required()->columnSpanFull(),
                Toggle::make('is_published')->default(true),
                Toggle::make('no_index')
                    ->label('Hide from search engines (noindex)')
                    ->helperText('For a page that should stay live but not show up in Google/Bing — a thank-you page, a duplicate, etc.'),

                Section::make('Search & social preview')
                    ->description('How this page appears in search results and when shared on social media — RankMath-style controls.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextInput::make('focus_keyword')
                            ->label('Focus keyword')
                            ->live(onBlur: true)
                            ->helperText('The main phrase this page should rank for — drives the checklist below, not stored anywhere else.'),
                        TextInput::make('seo_title')
                            ->label('SEO title')
                            ->maxLength(70)
                            ->live(onBlur: true)
                            ->helperText('Falls back to the page title above when left blank.')
                            ->hintAction(
                                Action::make('generate_seo_meta')
                                    ->label('Generate with AI')
                                    ->icon('heroicon-o-sparkles')
                                    ->action(function ($set, $get) {
                                        try {
                                            $suggestion = app(SeoMetaAssistant::class)->suggest(
                                                (string) $get('title'),
                                                strip_tags((string) $get('content')),
                                                $get('focus_keyword') ?: null,
                                            );
                                        } catch (AIGenerationException $e) {
                                            Notification::make()->title('Could not generate suggestions')->body($e->getMessage())->danger()->send();

                                            return;
                                        }

                                        $set('seo_title', $suggestion['seo_title']);
                                        $set('meta_description', $suggestion['meta_description']);
                                        Notification::make()->title('Suggestions added — review before saving')->success()->send();
                                    })
                            ),
                        Textarea::make('meta_description')->rows(2)->columnSpanFull()->live(onBlur: true)
                            ->helperText('Shown in Google search results. Leave blank to fall back to the site default.'),
                        WebpFileUpload::make('og_image_path')
                            ->label('Social share image (OG image)')
                            ->image()
                            ->disk('public')
                            ->directory('seo')
                            ->columnSpanFull()
                            ->helperText('Shown when this specific page is shared on social media. Leave blank to fall back to the site default.'),
                        Placeholder::make('seo_checklist')
                            ->label('On-page checklist')
                            ->columnSpanFull()
                            ->content(function ($get) {
                                $title = (string) ($get('seo_title') ?: $get('title'));
                                $description = (string) $get('meta_description');
                                $keyword = (string) $get('focus_keyword');
                                $content = strip_tags((string) $get('content'));

                                $checks = [
                                    ['pass' => strlen($title) > 0 && strlen($title) <= 60, 'label' => 'Title is under 60 characters'],
                                    ['pass' => strlen($description) >= 50 && strlen($description) <= 155, 'label' => 'Meta description is 50-155 characters'],
                                    ['pass' => $keyword === '' || str_contains(mb_strtolower($title), mb_strtolower($keyword)), 'label' => 'Focus keyword appears in the title'],
                                    ['pass' => $keyword === '' || str_contains(mb_strtolower($content), mb_strtolower($keyword)), 'label' => 'Focus keyword appears in the page content'],
                                    ['pass' => str_word_count($content) >= 100, 'label' => 'Page has at least 100 words of content'],
                                ];

                                return new HtmlString(
                                    collect($checks)
                                        ->map(fn (array $check) => ($check['pass'] ? '✓ ' : '✗ ').e($check['label']))
                                        ->implode('<br>')
                                );
                            }),
                    ]),
            ]);
    }
}
