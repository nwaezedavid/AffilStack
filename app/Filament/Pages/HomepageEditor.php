<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\BrandLogos\BrandLogoResource;
use App\Filament\Resources\FaqItems\FaqItemResource;
use App\Filament\Resources\HomepageFeatures\HomepageFeatureResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Support\WebsiteFields;
use App\Models\SiteSetting;
use App\Services\Blog\BlogFeed;
use App\Support\Homepage\HomepageContent;
use App\Support\OutboundUrlGuard;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Website → Homepage. One tab per homepage section, in the order they
 * appear on the page top to bottom, each with a "View this section on the
 * site" link. Every word, button, icon and picture the homepage shows is
 * here, pre-filled with what's live right now — change a word and save.
 * Image spots say exactly where they appear, show the picture that's live
 * (an upload or the built-in screenshot), the size to make and what it
 * should show.
 *
 * Everything is a SiteSetting (see HomepageContent for keys and default
 * copy). A value identical to the built-in default is stored blank so the
 * page keeps following the shipped copy until someone actually edits it.
 */
class HomepageEditor extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

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
        'home_hero_note', 'home_hero_works_with', 'hero_media_type', 'hero_image_path', 'hero_image_alt', 'hero_youtube_url',
        'home_logos_heading',
        'home_how_eyebrow', 'home_how_heading', 'home_how_subheading', 'home_how_step_link_label', 'home_how_footer_text', 'home_how_footer_link',
        'home_features_eyebrow', 'home_features_heading', 'home_features_subheading', 'home_features_link_label', 'home_features_all_label',
        'home_video_eyebrow', 'home_video_url', 'home_video_heading', 'home_video_subheading', 'home_video_points',
        'home_video_poster_path', 'home_video_poster_alt',
        'home_stats_heading',
        'home_testimonials_eyebrow', 'home_testimonials_heading',
        'home_pricing_eyebrow', 'home_pricing_heading', 'home_pricing_subheading', 'home_pricing_featured_label', 'home_pricing_button_label', 'home_pricing_link_label',
        'home_faq_eyebrow', 'home_faq_heading', 'home_faq_subheading', 'home_faq_count', 'home_faq_help_label', 'home_faq_contact_label',
        'home_blog_eyebrow', 'home_blog_link_label', 'home_blog_read_label',
        'blog_feed_url', 'blog_feed_title', 'blog_feed_subtitle', 'blog_feed_count',
        'home_apps_phone_text', 'home_apps_phone_link',
        'home_cta_heading', 'home_cta_subtext', 'home_cta_button_label', 'home_cta_button_url', 'home_cta_secondary_label',
    ];

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $defaults = HomepageContent::defaults();
        $values = [];

        foreach (static::$textKeys as $key) {
            // Show the words that are live right now — the built-in copy
            // until someone changes it — so editing is just changing a word.
            $values[$key] = array_key_exists($key, $defaults) && ! in_array($key, ['home_hero_primary_url', 'home_cta_button_url'], true)
                ? HomepageContent::text($key)
                : SiteSetting::get($key);
        }

        $values['hero_media_type'] = $values['hero_media_type'] ?: 'none';
        $values['home_faq_count'] = (string) HomepageContent::faqCount();
        $values['blog_feed_count'] = (string) BlogFeed::postCount();
        $values['blog_feed_title'] = $values['blog_feed_title'] ?: BlogFeed::DEFAULT_TITLE;
        $values['blog_feed_subtitle'] = $values['blog_feed_subtitle'] ?: BlogFeed::DEFAULT_SUBTITLE;
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
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Homepage sections')
                    ->persistTabInQueryString()
                    ->scrollable()
                    ->tabs([
                        $this->sectionsTab(),
                        $this->announcementTab(),
                        $this->heroTab(),
                        $this->logosTab(),
                        $this->howItWorksTab(),
                        $this->featuresTab(),
                        $this->videoTab(),
                        $this->statsTab(),
                        $this->testimonialsTab(),
                        $this->pricingTab(),
                        $this->faqTab(),
                        $this->blogTab(),
                        $this->appsTab(),
                        $this->ctaTab(),
                    ]),
            ]);
    }

    protected function sectionsTab(): Tab
    {
        return Tab::make('1. Layout & order')
            ->icon(Heroicon::OutlinedQueueList)
            ->components([
                Callout::make('Switch sections on or off and put them in any order')
                    ->info()
                    ->description('Use the arrows to move a section up or down. Sections that need content (logos, video, testimonials, FAQ, blog, apps) also hide themselves automatically until that content exists, so you never show an empty block. The tabs above follow the page from top to bottom — open one to edit its words and pictures.'),
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
        return Tab::make('2. Announcement bar')
            ->icon(Heroicon::OutlinedMegaphone)
            ->components([
                Section::make('The thin bar above the menu on every public page')
                    ->description('Great for launches, discounts or a webinar. Leave the message empty to hide the bar.')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewAnnouncement', '')])
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
                        WebsiteFields::linkInput('header_announcement_link_url', 'Link address (optional)', '/pricing'),
                    ]),
            ]);
    }

    protected function heroTab(): Tab
    {
        return Tab::make('3. Hero')
            ->icon(Heroicon::OutlinedSparkles)
            ->components([
                Section::make('Words and buttons at the very top of the homepage')
                    ->description('Wrap words in *asterisks* to colour them gold, e.g. "Find buyers. *Get paid.*"')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewHero', 'hero')])
                    ->components([
                        TextInput::make('home_hero_eyebrow')->label('Small label above the headline')->maxLength(80)->columnSpanFull(),
                        Textarea::make('hero_headline')->label('Headline')->rows(2)->maxLength(140)->required()->columnSpanFull(),
                        Textarea::make('hero_subheadline')->label('Subheadline')->rows(3)->maxLength(320)->columnSpanFull(),
                        TextInput::make('home_hero_primary_label')->label('Main button text')->maxLength(40),
                        WebsiteFields::linkInput('home_hero_primary_url', 'Main button link', '/pricing (default)'),
                        TextInput::make('home_hero_secondary_label')->label('Second button text')->maxLength(40),
                        WebsiteFields::linkInput('home_hero_secondary_url', 'Second button link', '#how-it-works'),
                        TextInput::make('home_hero_note')->label('Reassurance line under the buttons')->maxLength(140)->columnSpanFull(),
                        TextInput::make('home_hero_works_with')->label('Label above the channel logos')->maxLength(80)->columnSpanFull(),
                    ]),
                Section::make('Picture or video beside the headline')
                    ->columns(2)
                    ->components([
                        Select::make('hero_media_type')
                            ->label('Show')
                            ->options([
                                'none' => 'Designed product preview (built in — no upload needed)',
                                'image' => 'An image I upload',
                                'youtube' => 'A YouTube video',
                            ])
                            ->default('none')
                            ->selectablePlaceholder(false)
                            ->live()
                            ->columnSpanFull(),
                        WebsiteFields::imageSlot('hero_image_path', 'Homepage → Hero → picture beside the headline', '1600 × 1000 px (16:10)', 'A clean screenshot of the AffilStack dashboard (e.g. product research results or the earnings page), or a bright photo of an African entrepreneur working on a laptop. It sits on a dark navy background in a rounded frame, so keep text away from the edges.', 'offer-research.webp')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->columnSpanFull(),
                        WebsiteFields::imageUpload('hero_image_path', 'Hero image', 'homepage', '1600 × 1000 px')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->columnSpanFull(),
                        TextInput::make('hero_image_alt')
                            ->label('Image description (alt text)')
                            ->maxLength(150)
                            ->placeholder('AffilStack dashboard showing product research results')
                            ->helperText('Describe what the picture shows, for screen readers and Google Images.')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'image')
                            ->columnSpanFull(),
                        WebsiteFields::youTubeInput('hero_youtube_url', 'YouTube video for the hero')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'youtube')
                            ->columnSpanFull(),
                        WebsiteFields::youTubePreview('hero_youtube_url')
                            ->visible(fn (Get $get): bool => $get('hero_media_type') === 'youtube')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function logosTab(): Tab
    {
        return Tab::make('4. Logos')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->components([
                Section::make('The scrolling row of company logos')
                    ->description('Logos are resized automatically when you upload them, so they all line up at the same size.')
                    ->headerActions([$this->viewAction('viewLogos', 'logos')])
                    ->components([
                        TextInput::make('home_logos_heading')->label('Small heading above the logos')->maxLength(80),
                        Actions::make([
                            Action::make('manageLogos')->label('Add or change logos')->icon(Heroicon::OutlinedPhoto)->url(BrandLogoResource::getUrl())->color('gray'),
                        ]),
                    ]),
            ]);
    }

    protected function howItWorksTab(): Tab
    {
        return Tab::make('5. How it works')
            ->icon(Heroicon::OutlinedMap)
            ->components([
                Section::make('Heading')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewHow', 'how-it-works')])
                    ->components([
                        TextInput::make('home_how_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('home_how_heading')->label('Heading')->helperText('*Asterisks* colour words blue.')->maxLength(120),
                        TextInput::make('home_how_subheading')->label('Subheading')->maxLength(200)->columnSpanFull(),
                        TextInput::make('home_how_step_link_label')->label('Link under each step')->maxLength(40),
                        TextInput::make('home_how_footer_text')->label('Line under the steps')->maxLength(80),
                        TextInput::make('home_how_footer_link')->label('Learning Centre link text')->maxLength(80)->columnSpanFull(),
                    ]),
                Section::make('Steps — each with its screenshot')
                    ->description('Visitors click a step to see its screenshot. Every step ships with a real screenshot of AffilStack; upload your own only to replace it.')
                    ->components([
                        Repeater::make('home_steps')
                            ->hiddenLabel()
                            ->minItems(2)
                            ->maxItems(6)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->addActionLabel('Add a step')
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->columns(3)
                            ->schema([
                                Select::make('icon')->options(HomepageContent::iconOptions())->default('sparkles')->required(),
                                TextInput::make('title')->required()->maxLength(60)->columnSpan(2),
                                Textarea::make('description')->required()->rows(2)->maxLength(260)->columnSpanFull(),
                                WebsiteFields::linkInput('link_url', 'Link (optional)', '/features/offer-research')
                                    ->helperText('Where "'.HomepageContent::text('home_how_step_link_label').'" goes — usually the feature\'s page.')
                                    ->columnSpanFull(),
                                Hidden::make('default_image'),
                                WebsiteFields::imageSlot(
                                    'image_path',
                                    fn (Get $get): string => 'Homepage → How it works → screenshot for "'.($get('title') ?: 'this step').'"',
                                    '1600 × 1000 px (16:10), a browser-sized screenshot',
                                    'A screenshot of AffilStack doing exactly what this step describes, with realistic data.',
                                    fn (Get $get): ?string => $get('default_image'),
                                )->columnSpanFull(),
                                WebsiteFields::imageUpload('image_path', 'Replace this step\'s screenshot', 'homepage/steps', '1600 × 1000 px')->columnSpan(2),
                                TextInput::make('image_alt')->label('Screenshot description (alt text)')->maxLength(160),
                            ]),
                    ]),
            ]);
    }

    protected function featuresTab(): Tab
    {
        return Tab::make('6. Features')
            ->icon(Heroicon::OutlinedRectangleStack)
            ->components([
                Section::make('Heading and buttons')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewFeatures', 'features')])
                    ->components([
                        TextInput::make('home_features_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('home_features_heading')->label('Heading')->maxLength(120),
                        Textarea::make('home_features_subheading')->label('Subheading')->rows(2)->maxLength(260)->columnSpanFull(),
                        TextInput::make('home_features_link_label')->label('Link on each card')->maxLength(40),
                        TextInput::make('home_features_all_label')->label('Button under the cards (to /features)')->maxLength(60),
                    ]),
                Callout::make('The feature cards themselves')
                    ->description('Each card has an icon, a title, a short description, a screenshot (built in, or upload your own / add a YouTube video) and a "Learn more" link. Add, reorder or hide cards under Website → Homepage feature cards.')
                    ->footerActions([
                        Action::make('manageFeatures')->label('Edit feature cards')->url(HomepageFeatureResource::getUrl())->color('gray'),
                    ]),
            ]);
    }

    protected function videoTab(): Tab
    {
        return Tab::make('7. Product video')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->components([
                Section::make('Your YouTube video')
                    ->description('Paste the link and save — the section appears on the homepage straight away, with a fast-loading thumbnail that plays the video when clicked. Remove the link to hide the section.')
                    ->headerActions([$this->viewAction('viewVideo', 'see-it-in-action')])
                    ->components([
                        WebsiteFields::youTubeInput('home_video_url', 'YouTube video link')
                            ->helperText('Paste any YouTube link — watch, youtu.be, shorts or embed.'),
                        WebsiteFields::youTubePreview('home_video_url'),
                    ]),
                Section::make('Words around the video')
                    ->columns(2)
                    ->components([
                        TextInput::make('home_video_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('home_video_heading')->label('Heading')->maxLength(100),
                        TextInput::make('home_video_subheading')->label('Subheading')->maxLength(200)->columnSpanFull(),
                        Textarea::make('home_video_points')->label('Highlights (one per line, up to 3)')->rows(3)->columnSpanFull(),
                    ]),
                Section::make('Custom thumbnail (optional)')
                    ->description('By default YouTube\'s own thumbnail is used.')
                    ->columns(2)
                    ->components([
                        WebsiteFields::imageSlot('home_video_poster_path', 'Homepage → Product video → thumbnail before play', '1280 × 720 px (16:9), under 500 KB', 'A frame from the video or a dashboard screenshot with a short 3–5 word title such as "Your first sale in 5 steps". Keep the centre clear — a big play button sits on top.')->columnSpanFull(),
                        WebsiteFields::imageUpload('home_video_poster_path', 'Thumbnail image', 'homepage', '1280 × 720 px'),
                        TextInput::make('home_video_poster_alt')->label('Thumbnail description (alt text)')->maxLength(150)->placeholder('Walkthrough of the AffilStack dashboard'),
                    ]),
            ]);
    }

    protected function statsTab(): Tab
    {
        return Tab::make('8. Results band')
            ->icon(Heroicon::OutlinedChartBar)
            ->components([
                Callout::make('Only real numbers')
                    ->warning()
                    ->description('The defaults are true facts about the product. Swap them for real results (members, commissions tracked, countries) as they grow — never invented figures.'),
                Section::make('Big numbers on a navy band')
                    ->headerActions([$this->viewAction('viewStats', 'results')])
                    ->components([
                        TextInput::make('home_stats_heading')->label('Heading')->maxLength(100),
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
                    ]),
            ]);
    }

    protected function testimonialsTab(): Tab
    {
        return Tab::make('9. Testimonials')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->components([
                Section::make('What customers say')
                    ->description('Appears once at least 3 testimonials are published.')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewTestimonials', 'testimonials')])
                    ->components([
                        TextInput::make('home_testimonials_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('home_testimonials_heading')->label('Heading')->maxLength(100),
                        Actions::make([
                            Action::make('manageTestimonials')->label('Add or change testimonials')->url(TestimonialResource::getUrl())->color('gray'),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    protected function pricingTab(): Tab
    {
        return Tab::make('10. Pricing teaser')
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->components([
                Section::make('Up to three plans with a link to /pricing')
                    ->description('Plan names, prices and credits come straight from Billing → Plans, so they always match the Pricing page.')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewPricing', 'pricing')])
                    ->components([
                        TextInput::make('home_pricing_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('home_pricing_heading')->label('Heading')->maxLength(100),
                        Textarea::make('home_pricing_subheading')->label('Subheading')->rows(2)->maxLength(260)->columnSpanFull(),
                        TextInput::make('home_pricing_featured_label')->label('Badge on the featured plan')->maxLength(30),
                        TextInput::make('home_pricing_button_label')->label('Button on each plan')->maxLength(30),
                        TextInput::make('home_pricing_link_label')->label('Link under the plans')->maxLength(60)->columnSpanFull(),
                    ]),
            ]);
    }

    protected function faqTab(): Tab
    {
        return Tab::make('11. FAQ teaser')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->components([
                Section::make('Your first few published questions')
                    ->description('The questions themselves are under Website → FAQ questions; the full list is on the FAQ page.')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewFaq', 'faq')])
                    ->components([
                        TextInput::make('home_faq_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('home_faq_heading')->label('Heading')->maxLength(100),
                        TextInput::make('home_faq_subheading')->label('Subheading')->maxLength(200),
                        Select::make('home_faq_count')->label('Questions to show')->options(array_combine(range(3, 6), range(3, 6)))->selectablePlaceholder(false),
                        TextInput::make('home_faq_help_label')->label('Main button (to the FAQ page)')->maxLength(40),
                        TextInput::make('home_faq_contact_label')->label('Second button (to Contact)')->maxLength(40),
                        Actions::make([
                            Action::make('manageFaqs')->label('Edit FAQ questions')->url(FaqItemResource::getUrl())->color('gray'),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    protected function blogTab(): Tab
    {
        return Tab::make('12. Blog articles')
            ->icon(Heroicon::OutlinedNewspaper)
            ->components([
                Section::make('Latest articles from your WordPress blog')
                    ->description('Posts are fetched from your blog automatically (refreshed every hour) — publish on the blog and they show up here with their featured image. If the blog is ever unreachable, the section quietly hides instead of breaking the page.')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewBlog', 'blog')])
                    ->components([
                        Toggle::make('blog_feed_enabled')->label('Show latest blog articles on the homepage')->default(true)->columnSpanFull(),
                        TextInput::make('blog_feed_url')
                            ->label('Blog address')
                            ->placeholder('https://blog.affilstack.com')
                            ->helperText('The home page of your WordPress blog. Also adds "Blog" to the Resources menu and the footer. Left empty, the Blog URL from SEO settings is used.')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $normalized = BlogFeed::normalizeBaseUrl($value);

                                    if (filled($value) && (! $normalized || OutboundUrlGuard::problem($normalized))) {
                                        $fail('Enter the public address of your blog, e.g. https://blog.affilstack.com');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        TextInput::make('home_blog_eyebrow')->label('Small label')->maxLength(60),
                        TextInput::make('blog_feed_title')->label('Section heading')->maxLength(100),
                        TextInput::make('blog_feed_subtitle')->label('Subheading')->maxLength(200)->columnSpanFull(),
                        Select::make('blog_feed_count')->label('Articles to show')->options(array_combine(range(3, 6), range(3, 6)))->selectablePlaceholder(false),
                        TextInput::make('home_blog_link_label')->label('Button to the blog')->maxLength(40),
                        TextInput::make('home_blog_read_label')->label('Link on each article')->maxLength(40),
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
        return Tab::make('13. Mobile apps')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->components([
                Section::make('The apps band')
                    ->description('App name, pitch and store links live under Website → Mobile Apps. The band, footer badges and dashboard card appear the moment a link is saved.')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewApps', 'get-the-app')])
                    ->components([
                        TextInput::make('home_apps_phone_text')->label('Line under the badges')->maxLength(60),
                        TextInput::make('home_apps_phone_link')->label('Link text')->maxLength(80),
                        Actions::make([
                            Action::make('manageApps')->label('Open Mobile Apps settings')->url(MobileAppSettings::getUrl())->color('gray'),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    protected function ctaTab(): Tab
    {
        return Tab::make('14. Final call to action')
            ->icon(Heroicon::OutlinedCursorArrowRays)
            ->components([
                Section::make('The closing banner at the bottom of the page')
                    ->columns(2)
                    ->headerActions([$this->viewAction('viewCta', 'start')])
                    ->components([
                        TextInput::make('home_cta_heading')->label('Heading')->maxLength(100)->columnSpanFull(),
                        Textarea::make('home_cta_subtext')->label('Supporting line')->rows(2)->maxLength(200)->columnSpanFull(),
                        TextInput::make('home_cta_button_label')->label('Main button text')->maxLength(40),
                        WebsiteFields::linkInput('home_cta_button_url', 'Main button link', '/pricing (default)'),
                        TextInput::make('home_cta_secondary_label')->label('Second button text (to Contact)')->maxLength(40),
                    ]),
            ]);
    }

    protected function viewAction(string $name, string $anchor): Action
    {
        return Action::make($name)
            ->label('View this section on the site')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->size('sm')
            ->url(fn (): string => route('home').'?preview='.now()->timestamp.($anchor !== '' ? '#'.$anchor : ''))
            ->openUrlInNewTab();
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
        $defaults = HomepageContent::defaults() + [
            'blog_feed_title' => BlogFeed::DEFAULT_TITLE,
            'blog_feed_subtitle' => BlogFeed::DEFAULT_SUBTITLE,
        ];

        foreach (static::$textKeys as $key) {
            $value = $data[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            // Unchanged built-in copy is stored blank, so it keeps following
            // the shipped default.
            if (is_string($value) && array_key_exists($key, $defaults) && $value === trim((string) $defaults[$key])) {
                $value = '';
            }

            SiteSetting::set($key, $value);
        }

        SiteSetting::setFlag('blog_feed_enabled', (bool) ($data['blog_feed_enabled'] ?? false));

        SiteSetting::set('home_sections', json_encode(array_values(array_map(
            fn (array $row): array => ['key' => (string) ($row['key'] ?? ''), 'enabled' => (bool) ($row['enabled'] ?? true)],
            $data['home_sections'] ?? [],
        ))));
        SiteSetting::set('home_steps', json_encode(array_values(array_map(
            fn (array $row): array => array_filter($row, fn ($value): bool => $value !== null && $value !== '' && $value !== []),
            $data['home_steps'] ?? [],
        ))));
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
                ->url(fn (): string => route('home').'?preview='.now()->timestamp)
                ->openUrlInNewTab(),
        ];
    }
}
