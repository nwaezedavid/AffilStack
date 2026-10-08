<?php

namespace App\Filament\Resources\FeaturePages\Schemas;

use App\Filament\Support\WebsiteFields;
use App\Models\FeaturePage;
use App\Support\Homepage\HomepageContent;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * A feature page, in the order it reads on the site: menu entry → header
 * → detailed sections (each with a screenshot and a video slot) → how to
 * use it → FAQ → closing call to action → SEO.
 */
class FeaturePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Feature page')
                    ->persistTabInQueryString()
                    ->tabs([
                        static::menuTab(),
                        static::heroTab(),
                        static::sectionsTab(),
                        static::stepsTab(),
                        static::faqTab(),
                        static::ctaTab(),
                        static::seoTab(),
                    ]),
            ]);
    }

    protected static function view(string $name, string $anchor = ''): Actions
    {
        return WebsiteFields::viewOnSite($name, fn (?FeaturePage $record): string => $record ? $record->url().($anchor !== '' ? '#'.$anchor : '') : route('features.index'));
    }

    protected static function menuTab(): Tab
    {
        return Tab::make('1. Menu & address')
            ->icon(Heroicon::OutlinedBars3)
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Tool name')
                    ->required()
                    ->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    })
                    ->helperText('Shown in the Tools menu, on the /features overview and in the breadcrumb.'),
                TextInput::make('slug')
                    ->label('Web address')
                    ->prefix('/features/')
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9-]+$/')
                    ->unique(ignoreRecord: true)
                    ->helperText('Lowercase letters, numbers and dashes. Changing it breaks old links — add a redirect if you do.'),
                Select::make('menu_group')->label('Tools menu column')->options(FeaturePage::GROUPS)->required()->default('grow'),
                Select::make('icon')
                    ->label('Icon')
                    ->options(static::iconOptions())
                    ->searchable()
                    ->required()
                    ->default('sparkles'),
                TextInput::make('menu_description')
                    ->label('One-line description in the menu')
                    ->maxLength(80)
                    ->columnSpanFull()
                    ->helperText('Appears under the name in the Tools menu and on the /features cards.'),
                Toggle::make('show_in_menu')->label('Show in the Tools menu')->default(true),
                Toggle::make('is_published')->label('Page is live')->default(true)->helperText('Off hides the page, its menu entry and its sitemap entry.'),
                static::view('viewMenu'),
            ]);
    }

    protected static function heroTab(): Tab
    {
        return Tab::make('2. Page header')
            ->icon(Heroicon::OutlinedSparkles)
            ->components([
                Section::make('Words')
                    ->description('Wrap words in *asterisks* to colour them gold.')
                    ->columns(2)
                    ->components([
                        TextInput::make('hero_eyebrow')->label('Small label above the headline')->maxLength(80),
                        TextInput::make('hero_headline')->label('Headline')->required()->maxLength(140),
                        Textarea::make('hero_subheadline')->label('Subheadline')->rows(3)->maxLength(400)->columnSpanFull(),
                        static::view('viewHero'),
                    ]),
                Section::make('Screenshot (or video) beside the headline')
                    ->columns(2)
                    ->components([
                        Hidden::make('hero_default_image'),
                        WebsiteFields::imageSlot('hero_image_path', fn (Get $get): string => 'Features → '.($get('name') ?: 'this tool').' → page header screenshot', '1600 × 1000 px (16:10), a browser-sized screenshot', 'This tool in action inside AffilStack, with realistic data. It is also the card image on /features and the social-share picture.', fn (Get $get): ?string => $get('hero_default_image'))->columnSpanFull(),
                        WebsiteFields::imageUpload('hero_image_path', 'Replace the header screenshot', 'features', '1600 × 1000 px'),
                        TextInput::make('hero_image_alt')->label('Screenshot description (alt text)')->maxLength(200),
                        WebsiteFields::youTubeInput('hero_video_url', 'Header video (optional)')
                            ->helperText('Paste any YouTube link to show a video here instead of the screenshot (the screenshot becomes its cover).')
                            ->columnSpanFull(),
                        WebsiteFields::youTubePreview('hero_video_url')->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function sectionsTab(): Tab
    {
        return Tab::make('3. Detailed sections')
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->components([
                static::view('viewSections'),
                Repeater::make('sections')
                    ->hiddenLabel()
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->addActionLabel('Add a section')
                    ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)
                    ->columns(2)
                    ->schema([
                        TextInput::make('heading')->required()->maxLength(120)->columnSpanFull(),
                        Textarea::make('body')->label('Paragraph')->rows(3)->maxLength(800)->columnSpanFull(),
                        Textarea::make('bullets')
                            ->label('Bullet points (one per line)')
                            ->rows(4)
                            ->formatStateUsing(fn ($state): string => is_array($state) ? implode("\n", $state) : (string) $state)
                            ->dehydrateStateUsing(fn ($state): array => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $state) ?: []))))
                            ->columnSpanFull(),
                        Hidden::make('default_image'),
                        WebsiteFields::imageSlot('image_path', fn (Get $get): string => 'Feature page → section "'.($get('heading') ?: 'new section').'" → screenshot', '1600 × 1000 px (16:10)', 'A screenshot of AffilStack showing exactly what this section describes.', fn (Get $get): ?string => $get('default_image'))->columnSpanFull(),
                        WebsiteFields::imageUpload('image_path', 'Replace this screenshot', 'features', '1600 × 1000 px'),
                        TextInput::make('image_alt')->label('Screenshot description (alt text)')->maxLength(200),
                        WebsiteFields::youTubeInput('video_url', 'Video for this section (optional)')
                            ->helperText('Paste any YouTube link and it appears under this section\'s screenshot. Leave empty and nothing shows — add it whenever the video is ready.')
                            ->columnSpanFull(),
                        WebsiteFields::youTubePreview('video_url')->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function stepsTab(): Tab
    {
        return Tab::make('4. How to use it')
            ->icon(Heroicon::OutlinedListBullet)
            ->components([
                static::view('viewSteps', 'steps-heading'),
                Repeater::make('steps')
                    ->hiddenLabel()
                    ->reorderableWithButtons()
                    ->addActionLabel('Add a step')
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columns(3)
                    ->schema([
                        TextInput::make('title')->required()->maxLength(80),
                        Textarea::make('description')->rows(2)->maxLength(300)->columnSpan(2),
                    ]),
            ]);
    }

    protected static function faqTab(): Tab
    {
        return Tab::make('5. FAQ')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->components([
                static::view('viewFaq', 'feature-faq-heading'),
                Repeater::make('faqs')
                    ->hiddenLabel()
                    ->reorderableWithButtons()
                    ->addActionLabel('Add a question')
                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                    ->schema([
                        TextInput::make('question')->required()->maxLength(200),
                        Textarea::make('answer')->required()->rows(3)->maxLength(1000),
                    ]),
            ]);
    }

    protected static function ctaTab(): Tab
    {
        return Tab::make('6. Closing banner')
            ->icon(Heroicon::OutlinedCursorArrowRays)
            ->columns(2)
            ->components([
                TextInput::make('cta_heading')->label('Heading')->maxLength(120)->columnSpanFull(),
                TextInput::make('cta_text')->label('Supporting line')->maxLength(200)->columnSpanFull(),
                TextInput::make('cta_label')->label('Button text')->maxLength(40)->placeholder('See plans & pricing'),
                WebsiteFields::linkInput('cta_url', 'Button link', '/pricing (default)'),
                static::view('viewCta'),
            ]);
    }

    protected static function seoTab(): Tab
    {
        return Tab::make('7. SEO')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->components([
                TextInput::make('seo_title')->label('Title in Google')->maxLength(70)->helperText('Leave empty to use "Tool name — headline".'),
                Textarea::make('meta_description')->label('Description in Google')->rows(2)->maxLength(160)->helperText('Leave empty to use the subheadline.'),
                static::sortOrder(),
            ]);
    }

    protected static function sortOrder(): TextInput
    {
        return TextInput::make('sort_order')->label('Order (lower first)')->numeric()->default(0);
    }

    /**
     * @return array<string, string>
     */
    protected static function iconOptions(): array
    {
        $extra = [
            'magnifying-glass-circle' => 'Magnifying glass circle (research)', 'puzzle-piece' => 'Puzzle piece (extension)', 'briefcase' => 'Briefcase (LinkedIn)',
            'paper-airplane' => 'Paper plane (campaigns)', 'inbox-arrow-down' => 'Inbox (replies)', 'document-text' => 'Document (articles)', 'newspaper' => 'Newspaper (posts)',
            'play-circle' => 'Play (YouTube)', 'musical-note' => 'Music note (TikTok)', 'chat-bubble-bottom-center-text' => 'Chat bubble (threads)', 'photo' => 'Photo (pins)',
            'envelope-open' => 'Open envelope (nurture)', 'cursor-arrow-rays' => 'Cursor (clicks)', 'calendar-days' => 'Calendar', 'eye' => 'Eye (competitors)',
            'globe-europe-africa' => 'Globe Africa (localization)', 'rectangle-stack' => 'Stack (swipe files)', 'code-bracket-square' => 'Code (API)',
        ];

        return HomepageContent::iconOptions() + $extra;
    }
}
