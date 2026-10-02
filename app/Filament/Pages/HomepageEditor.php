<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\BrandLogos\BrandLogoResource;
use App\Filament\Resources\FaqItems\FaqItemResource;
use App\Filament\Resources\HomepageFeatures\HomepageFeatureResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use App\Services\Blog\BlogFeed;
use App\Support\Homepage\HomepageContent;
use App\Support\OutboundUrlGuard;
use App\Support\YouTubeUrl;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * "In the admin dashboard area, add more features to enable me edit the
 * homepage whenever I want (Make it easy to do)." One screen, one tab per
 * homepage section: switch sections on/off and drag them into any order,
 * edit every heading and button, paste a YouTube link that just appears,
 * and upload images with a plain description of exactly what to create.
 *
 * Everything is stored as SiteSetting values (see HomepageContent for the
 * keys and default copy), so each save busts its own cache key and the
 * homepage reflects it on the next view. The blog feed's cached posts are
 * flushed too.
 */
class HomepageEditor extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Homepage';

    protected static ?string $title = 'Homepage editor';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.pages.homepage-editor';

    /**
     * Plain text SiteSetting keys edited on this page (form field name =
     * setting key).
     *
     * @var array<int, string>
     */
    protected static array $textKeys = [
        'header_announcement', 'header_announcement_link_label', 'header_announcement_link_url',
        'home_hero_eyebrow', 'hero_headline', 'hero_subheadline',
        'home_hero_primary_label', 'home_hero_primary_url', 'home_hero_secondary_label', 'home_hero_secondary_url',
        'home_hero_note', 'hero_media_type', 'hero_image_path', 'hero_image_alt', 'hero_youtube_url',
        'home_logos_heading',
        'home_how_heading', 'home_how_subheading',
        'home_features_heading', 'home_features_subheading',
        'home_video_url', 'home_video_heading', 'home_video_subheading', 'home_video_points',
        'home_video_poster_path', 'home_video_poster_alt',
        'home_stats_heading',
        'home_testimonials_heading',
        'home_pricing_heading', 'home_pricing_subheading',
        'home_faq_heading', 'home_faq_subheading', 'home_faq_count',
        'blog_feed_url', 'blog_feed_title', 'blog_feed_subtitle', 'blog_feed_count',
        'home_cta_heading', 'home_cta_subtext', 'home_cta_button_label', 'home_cta_button_url',
    ];

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $values = [];

        foreach (static::$textKeys as $key) {
            $values[$key] = SiteSetting::get($key);
        }

        $values['hero_media_type'] = $values['hero_media_type'] ?: 'none';
        $values['home_faq_count'] = (string) HomepageContent::faqCount();
        $values['blog_feed_count'] = (string) BlogFeed::postCount();
        $values['blog_feed_enabled'] = BlogFeed::isEnabled();
        $values['home_sections'] = array_map(
            fn (array $section): array => ['key' => $section['key'], 'enabled' => $section['enabled']],
            HomepageContent::sections(),
        );
        $values['home_steps'] = HomepageContent::steps();
        $values['home_stats'] = HomepageContent::stats();

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $defaults = HomepageContent::defaults();

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Homepage sections')
                    ->persistTabInQueryString()
                    ->scrollable()
                    ->tabs([
                        $this->sectionsTab(),
                        $this->announcementTab(),
                        $this->heroTab($defaults),
                        $this->howItWorksTab($defaults),
                        $this->featuresTab($defaults),
                        $this->videoTab($defaults),
                        $this->statsTab($defaults),
                        $this->socialProofTab($defaults),
                        $this->pricingFaqTab($defaults),
                        $this->blogTab(),
                        $this->appsTab(),
                        $this->ctaTab($defaults),
                    ]),
            ]);
    }

    protected function sectionsTab(): Tab
    {
        return Tab::make('Layout & order')
            ->icon(Heroicon::OutlinedQueueList)
            ->components([
                Callout::make('Switch sections on or off and put them in any order')
                    ->info()
                    ->description('Use the arrows to move a section up or down. Sections that need content (logos, video, testimonials, FAQ, blog, apps) also hide themselves automatically until that content exists, so you never show an empty block. Each section has its own tab above for its words and images.'),
                Repeater::make('home_sections')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderableWithButtons()
                    ->grid(2)
                    ->itemLabel(fn (array $state): string => (HomepageContent::SECTIONS[$state['key'] ?? '']['label'] ?? 'Section').(($state['enabled'] ?? true) ? '' : ' — hidden'))
                    ->schema([
                        Hidden::make('key'),
                        Toggle::make('enabled')
                            ->label('Show this section on the homepage')
                            ->helperText(fn (Get $get): string => HomepageContent::SECTIONS[$get('key')]['description'] ?? '')
                            ->live(),
                    ]),
            ]);
    }

    protected function announcementTab(): Tab
    {
        return Tab::make('Announcement bar')
            ->icon(Heroicon::OutlinedMegaphone)
            ->components([
                Section::make('The thin bar above the menu on every public page')
                    ->description('Great for launches, discounts or a webinar. Leave the message empty to hide the bar.')
                    ->columns(2)
                    ->components([
                        TextInput::make('header_announcement')
                            ->label('Message')
                            ->maxLength(160)
                            ->placeholder('New: find buyers on Google Maps in one click')
                            ->columnSpanFull(),
                        TextInput::make('header_announcement_link_label')
                            ->label('Link text (optional)')
                            ->maxLength(40)
                            ->placeholder('See what\'s new'),
                        $this->linkInput('header_announcement_link_url', 'Link address (optional)', '/pricing'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function heroTab(array $defaults): Tab
    {
        return Tab::make('Hero')
            ->icon(Heroicon::OutlinedSparkles)
            ->components([
                Section::make('Words')
                    ->description('Leave any field empty to keep the default text shown in grey.')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_hero_eyebrow')
                            ->label('Small label above the headline')
                            ->placeholder($defaults['home_hero_eyebrow'])
                            ->maxLength(80)
                            ->columnSpanFull(),
                        Textarea::make('hero_headline')
                            ->label('Headline')
                            ->rows(2)
                            ->maxLength(140)
                            ->placeholder($defaults['hero_headline'])
                            ->helperText('Wrap words in *asterisks* to colour them gold, e.g. "Find buyers. *Get paid.*"')
                            ->columnSpanFull(),
                        Textarea::make('hero_subheadline')
                            ->label('Subheadline')
                            ->rows(3)
                            ->maxLength(320)
                            ->placeholder($defaults['hero_subheadline'])
                            ->columnSpanFull(),
                        TextInput::make('home_hero_primary_label')->label('Main button text')->placeholder($defaults['home_hero_primary_label'])->maxLength(40),
                        $this->linkInput('home_hero_primary_url', 'Main button link', '/pricing (default)'),
                        TextInput::make('home_hero_secondary_label')->label('Second button text')->placeholder($defaults['home_hero_secondary_label'])->maxLength(40),
                        $this->linkInput('home_hero_secondary_url', 'Second button link', $defaults['home_hero_secondary_url']),
                        TextInput::make('home_hero_note')
                            ->label('Reassurance line under the buttons')
                            ->placeholder($defaults['home_hero_note'])
                            ->maxLength(140)
                            ->columnSpanFull(),
                    ]),
                Section::make('Image or video beside the headline')
                    ->columns(2)
                    ->components([
                        Select::make('hero_media_type')
                            ->label('Show')
                            ->options([
                                'none' => 'Designed product preview (no upload needed)',
                                'image' => 'An image I upload',
                                'youtube' => 'A YouTube video',
                            ])
                            ->default('none')
                            ->selectablePlaceholder(false)
                            ->live()
                            ->columnSpanFull(),
                        WebpFileUpload::make('hero_image_path')
                            ->label('Hero image')
                            ->image()
                            ->maxSize(3072)
                            ->disk('public')
                            ->directory('homepage')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->helperText('Create a 1600×1000 px (16:10) image: either a clean screenshot of the AffilStack dashboard showing product research results or the earnings chart, or a bright photo of an African entrepreneur working on a laptop. PNG, JPG or WebP under 1 MB. It sits on a dark navy background inside a rounded frame, so avoid text near the edges.')
                            ->columnSpanFull(),
                        TextInput::make('hero_image_alt')
                            ->label('Image description (alt text)')
                            ->maxLength(150)
                            ->placeholder('AffilStack dashboard showing product research results')
                            ->helperText('Describe what the picture shows, for screen readers and Google Images.')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->columnSpanFull(),
                        $this->youTubeInput('hero_youtube_url', 'YouTube video for the hero')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'youtube')
                            ->columnSpanFull(),
                        $this->youTubePreview('hero_youtube_url')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'youtube')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function howItWorksTab(array $defaults): Tab
    {
        return Tab::make('How it works')
            ->icon(Heroicon::OutlinedMap)
            ->components([
                Section::make('Heading')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_how_heading')->label('Heading')->placeholder($defaults['home_how_heading'])->helperText('*Asterisks* colour words gold.')->maxLength(120),
                        TextInput::make('home_how_subheading')->label('Subheading')->placeholder($defaults['home_how_subheading'])->maxLength(200),
                    ]),
                Section::make('Steps')
                    ->description('Shown left to right as a numbered journey. Four steps fit best.')
                    ->components([
                        Repeater::make('home_steps')
                            ->hiddenLabel()
                            ->minItems(2)
                            ->maxItems(6)
                            ->reorderableWithButtons()
                            ->addActionLabel('Add a step')
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->columns(3)
                            ->schema([
                                Select::make('icon')->options(HomepageContent::iconOptions())->default('sparkles')->required(),
                                TextInput::make('title')->required()->maxLength(60)->columnSpan(2),
                                Textarea::make('description')->required()->rows(2)->maxLength(200)->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function featuresTab(array $defaults): Tab
    {
        return Tab::make('Features')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->components([
                Section::make('Heading')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_features_heading')->label('Heading')->placeholder($defaults['home_features_heading'])->maxLength(120),
                        Textarea::make('home_features_subheading')->label('Subheading')->rows(2)->placeholder($defaults['home_features_subheading'])->maxLength(260),
                    ]),
                Callout::make('The feature cards themselves')
                    ->description('Add, reorder or hide cards — each with an icon, image or its own YouTube video — under Content → Homepage Features. Until you add one, six built-in cards describing the product are shown.')
                    ->footerActions([
                        Action::make('manageFeatures')->label('Manage feature cards')->url(HomepageFeatureResource::getUrl())->color('gray'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function videoTab(array $defaults): Tab
    {
        return Tab::make('Product video')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->components([
                Section::make('Your YouTube video')
                    ->description('Paste the link and save — the section appears on the homepage straight away, with a fast-loading thumbnail that plays the video when clicked. Remove the link to hide the section.')
                    ->components([
                        $this->youTubeInput('home_video_url', 'YouTube video link')
                            ->helperText('Paste any YouTube link — watch, youtu.be, shorts or embed — and it will appear in the "See AffilStack in action" section.'),
                        $this->youTubePreview('home_video_url'),
                    ]),
                Section::make('Words around the video')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_video_heading')->label('Heading')->placeholder($defaults['home_video_heading'])->maxLength(100),
                        TextInput::make('home_video_subheading')->label('Subheading')->placeholder($defaults['home_video_subheading'])->maxLength(200),
                        Textarea::make('home_video_points')
                            ->label('Highlights (one per line, up to 3)')
                            ->rows(3)
                            ->placeholder($defaults['home_video_points'])
                            ->columnSpanFull(),
                    ]),
                Section::make('Custom thumbnail (optional)')
                    ->description('By default YouTube\'s own thumbnail is used.')
                    ->columns(2)
                    ->components([
                        WebpFileUpload::make('home_video_poster_path')
                            ->label('Thumbnail image')
                            ->image()
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('homepage')
                            ->helperText('A 1280×720 px (16:9) cover image for the video: a frame from the video or a dashboard screenshot with a short 3–5 word title such as "Your first sale in 5 steps". Keep the centre clear — a big play button sits on top. JPG or WebP under 500 KB.'),
                        TextInput::make('home_video_poster_alt')
                            ->label('Thumbnail description (alt text)')
                            ->maxLength(150)
                            ->placeholder('Walkthrough of the AffilStack dashboard'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function statsTab(array $defaults): Tab
    {
        return Tab::make('Results band')
            ->icon(Heroicon::OutlinedChartBar)
            ->components([
                Callout::make('Only real numbers')
                    ->warning()
                    ->description('The defaults are true facts about the product. Swap them for real results (members, commissions tracked, countries) as they grow — never invented figures.'),
                TextInput::make('home_stats_heading')->label('Heading')->placeholder($defaults['home_stats_heading'])->maxLength(100),
                Repeater::make('home_stats')
                    ->label('Numbers')
                    ->minItems(2)
                    ->maxItems(4)
                    ->reorderableWithButtons()
                    ->addActionLabel('Add a number')
                    ->itemLabel(fn (array $state): ?string => trim(($state['value'] ?? '').' '.($state['label'] ?? '')) ?: null)
                    ->columns(3)
                    ->schema([
                        TextInput::make('value')->label('Big number')->required()->maxLength(12)->placeholder('5,000+'),
                        TextInput::make('label')->label('What it means')->required()->maxLength(80)->columnSpan(2)->placeholder('affiliates across Africa and the diaspora'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function socialProofTab(array $defaults): Tab
    {
        return Tab::make('Logos & testimonials')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->components([
                Section::make('Brand logo marquee')
                    ->components([
                        TextInput::make('home_logos_heading')->label('Small heading above the logos')->placeholder($defaults['home_logos_heading'])->maxLength(80),
                        Actions::make([
                            Action::make('manageLogos')->label('Manage logos')->url(BrandLogoResource::getUrl())->color('gray'),
                        ]),
                    ]),
                Section::make('Testimonials')
                    ->description('Appears once at least 3 testimonials are published.')
                    ->components([
                        TextInput::make('home_testimonials_heading')->label('Heading')->placeholder($defaults['home_testimonials_heading'])->maxLength(100),
                        Actions::make([
                            Action::make('manageTestimonials')->label('Manage testimonials')->url(TestimonialResource::getUrl())->color('gray'),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function pricingFaqTab(array $defaults): Tab
    {
        return Tab::make('Pricing & FAQ')
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->components([
                Section::make('Pricing teaser')
                    ->description('Prices come straight from your plans (Billing → Plans), so they always match /pricing.')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_pricing_heading')->label('Heading')->placeholder($defaults['home_pricing_heading'])->maxLength(100),
                        Textarea::make('home_pricing_subheading')->label('Subheading')->rows(2)->placeholder($defaults['home_pricing_subheading'])->maxLength(260),
                    ]),
                Section::make('FAQ teaser')
                    ->description('Shows your first published FAQs (Support → FAQ items) with a link to the full Help Centre.')
                    ->columns(3)
                    ->components([
                        TextInput::make('home_faq_heading')->label('Heading')->placeholder($defaults['home_faq_heading'])->maxLength(100),
                        TextInput::make('home_faq_subheading')->label('Subheading')->placeholder($defaults['home_faq_subheading'])->maxLength(200),
                        Select::make('home_faq_count')->label('Questions to show')->options(array_combine(range(3, 6), range(3, 6)))->selectablePlaceholder(false),
                        Actions::make([
                            Action::make('manageFaqs')->label('Manage FAQs')->url(FaqItemResource::getUrl())->color('gray'),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    protected function blogTab(): Tab
    {
        return Tab::make('Blog articles')
            ->icon(Heroicon::OutlinedNewspaper)
            ->components([
                Section::make('Latest articles from your WordPress blog')
                    ->description('Posts are fetched from your blog automatically (refreshed every hour) — publish on the blog and they show up here with their featured image. If the blog is ever unreachable, the section quietly hides instead of breaking the page.')
                    ->columns(2)
                    ->components([
                        Toggle::make('blog_feed_enabled')
                            ->label('Show latest blog articles on the homepage')
                            ->default(true)
                            ->columnSpanFull(),
                        TextInput::make('blog_feed_url')
                            ->label('Blog address')
                            ->placeholder('https://blog.affilstack.com')
                            ->helperText('The home page of your WordPress blog. Also adds a "Blog" link to the site menu and footer. Left empty, the Blog URL from SEO settings is used.')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $normalized = BlogFeed::normalizeBaseUrl($value);

                                    if (filled($value) && (! $normalized || OutboundUrlGuard::problem($normalized))) {
                                        $fail('Enter the public address of your blog, e.g. https://blog.affilstack.com');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        TextInput::make('blog_feed_title')->label('Section heading')->placeholder(BlogFeed::DEFAULT_TITLE)->maxLength(100),
                        Select::make('blog_feed_count')->label('Articles to show')->options(array_combine(range(3, 6), range(3, 6)))->selectablePlaceholder(false),
                        TextInput::make('blog_feed_subtitle')->label('Subheading')->placeholder(BlogFeed::DEFAULT_SUBTITLE)->maxLength(200)->columnSpanFull(),
                        Actions::make([
                            Action::make('testBlogConnection')
                                ->label('Test blog connection')
                                ->icon(Heroicon::OutlinedSignal)
                                ->action(fn () => $this->testBlogConnection()),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    protected function appsTab(): Tab
    {
        return Tab::make('Mobile apps')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->components([
                Callout::make('Store links live on their own page')
                    ->description('Paste your Google Play and App Store links under Content → Mobile Apps. The homepage apps band, the footer badges and the customer dashboard card appear the moment a link is saved.')
                    ->footerActions([
                        Action::make('manageApps')->label('Open Mobile Apps settings')->url(MobileAppSettings::getUrl())->color('gray'),
                    ]),
            ]);
    }

    /**
     * @param  array<string, string>  $defaults
     */
    protected function ctaTab(array $defaults): Tab
    {
        return Tab::make('Final call to action')
            ->icon(Heroicon::OutlinedCursorArrowRays)
            ->components([
                Section::make('The closing banner at the bottom of the page')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_cta_heading')->label('Heading')->placeholder($defaults['home_cta_heading'])->maxLength(100)->columnSpanFull(),
                        Textarea::make('home_cta_subtext')->label('Supporting line')->rows(2)->placeholder($defaults['home_cta_subtext'])->maxLength(200)->columnSpanFull(),
                        TextInput::make('home_cta_button_label')->label('Button text')->placeholder($defaults['home_cta_button_label'])->maxLength(40),
                        $this->linkInput('home_cta_button_url', 'Button link', '/pricing (default)'),
                    ]),
            ]);
    }

    protected function linkInput(string $name, string $label, string $placeholder): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder($placeholder)
            ->maxLength(255)
            // Site paths, in-page #anchors or http(s) links — never javascript:/data:.
            ->regex('#^(/(?![/\\\\])|https?://|\#[A-Za-z0-9_-])#i')
            ->validationMessages(['regex' => 'Use a site path like /pricing, an in-page link like #how-it-works, or a full https:// link.']);
    }

    protected function youTubeInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder('https://www.youtube.com/watch?v=...')
            ->prefixIcon(Heroicon::OutlinedPlay)
            ->maxLength(255)
            ->live(onBlur: true)
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! YouTubeUrl::idFrom((string) $value)) {
                        $fail('That doesn\'t look like a YouTube video link. Copy the address from the video page or the "Share" button.');
                    }
                },
            ]);
    }

    protected function youTubePreview(string $field): View
    {
        return View::make('filament.homepage.youtube-preview')
            ->viewData(fn (Get $get): array => ['url' => $get($field)]);
    }

    public function testBlogConnection(): void
    {
        $result = app(BlogFeed::class)->test($this->data['blog_feed_url'] ?? null);

        Notification::make()
            ->title($result['ok'] ? ($result['count'] > 0 ? 'Blog connected — '.$result['count'].' '.str('post')->plural($result['count']).' found' : 'Blog connected, but no posts yet') : 'Could not read the blog')
            ->body($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->persistent()
            ->send();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (static::$textKeys as $key) {
            $value = $data[$key] ?? null;
            SiteSetting::set($key, is_string($value) ? trim($value) : $value);
        }

        SiteSetting::setFlag('blog_feed_enabled', (bool) ($data['blog_feed_enabled'] ?? false));

        SiteSetting::set('home_sections', json_encode(array_values(array_map(
            fn (array $row): array => ['key' => (string) ($row['key'] ?? ''), 'enabled' => (bool) ($row['enabled'] ?? true)],
            $data['home_sections'] ?? [],
        ))));
        SiteSetting::set('home_steps', json_encode(array_values($data['home_steps'] ?? [])));
        SiteSetting::set('home_stats', json_encode(array_values($data['home_stats'] ?? [])));

        BlogFeed::flush();

        Notification::make()
            ->title('Homepage saved')
            ->body('Your changes are live. Use "Preview homepage" to see them.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview homepage')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                // A fresh query string sidesteps the browser's 5-minute
                // cache of the public page (see CachePublicPage).
                ->url(fn (): string => route('home').'?preview='.time())
                ->openUrlInNewTab(),
        ];
    }
}
