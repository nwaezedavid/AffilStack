<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Everything needed to get the site properly indexed and tracked, without a
 * code deploy: default meta description, social preview image, analytics
 * and Search Console verification, robots.txt content, and the URL of the
 * WordPress blog subdomain (linked from the nav and included in the
 * generated sitemap — the blog itself is not part of this app and manages
 * its own SEO in WordPress).
 */
class SeoSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'SEO';

    protected string $view = 'filament.pages.seo-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'seo_meta_description' => SiteSetting::get('seo_meta_description'),
            'seo_og_image_path' => SiteSetting::get('seo_og_image_path'),
            'seo_ga_id' => SiteSetting::get('seo_ga_id'),
            'seo_gsc_verification' => SiteSetting::get('seo_gsc_verification'),
            'seo_meta_pixel_id' => SiteSetting::get('seo_meta_pixel_id'),
            'seo_tiktok_pixel_id' => SiteSetting::get('seo_tiktok_pixel_id'),
            'seo_blog_url' => SiteSetting::get('seo_blog_url'),
            'seo_robots_txt' => SiteSetting::get('seo_robots_txt', "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')),
            'seo_social_facebook' => SiteSetting::get('seo_social_facebook'),
            'seo_social_twitter' => SiteSetting::get('seo_social_twitter'),
            'seo_social_linkedin' => SiteSetting::get('seo_social_linkedin'),
            'seo_social_instagram' => SiteSetting::get('seo_social_instagram'),
            'seo_social_youtube' => SiteSetting::get('seo_social_youtube'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Blog subdomain')
                    ->description('The blog lives on WordPress, not in this app — this just links to it and lists it in the sitemap.')
                    ->components([
                        TextInput::make('seo_blog_url')
                            ->label('Blog URL')
                            ->url()
                            ->placeholder('https://blog.affilstack.com')
                            ->helperText('Point your DNS for this subdomain at your WordPress install. Once set, "Blog" appears in the site nav automatically.'),
                    ]),

                Section::make('Search & social defaults')
                    ->components([
                        Textarea::make('seo_meta_description')
                            ->label('Default meta description')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Used on pages that don\'t set their own — aim for under 160 characters.'),
                        WebpFileUpload::make('seo_og_image_path')
                            ->label('Default social share image (OG image)')
                            ->image()
                            ->disk('public')
                            ->directory('seo')
                            ->helperText('Shown when a page is shared on social media or messaging apps. Recommended 1200×630.'),
                    ]),

                Section::make('Verification & analytics')
                    ->description('For a real Google connection that auto-creates the property and verifies Search Console for you, use Site > Site Analytics instead — these fields are the manual fallback and stay in sync with it either way.')
                    ->columns(2)
                    ->components([
                        TextInput::make('seo_ga_id')
                            ->label('Google Analytics measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('Only used when no Tag Manager container is connected under Site > Site Analytics — GTM takes over sitewide tracking once set up.'),
                        TextInput::make('seo_gsc_verification')
                            ->label('Google Search Console verification code')
                            ->helperText('The "content" value from Google\'s HTML tag verification method — paste just the code, not the full tag.'),
                    ]),

                Section::make('Ad conversion pixels')
                    ->description('Base tracking code only (page views) — configure conversion events from each platform\'s own ads manager.')
                    ->columns(2)
                    ->components([
                        TextInput::make('seo_meta_pixel_id')
                            ->label('Meta Pixel ID')
                            // Deliberately NOT ->numeric(): Filament's numeric
                            // state cast round-trips the value through
                            // floatval(), which silently corrupts a real
                            // 15-16 digit pixel ID into scientific notation
                            // (e.g. "123456789012345" -> "1.2345E+14"). This
                            // is an opaque identifier, not a value to do math
                            // on — validate its shape with a plain regex rule
                            // instead.
                            ->rule('regex:/^\d{5,20}$/')
                            ->placeholder('123456789012345')
                            ->helperText('From Meta Events Manager > Data Sources > your pixel > Settings.'),
                        TextInput::make('seo_tiktok_pixel_id')
                            ->label('TikTok Pixel ID')
                            ->placeholder('CXXXXXXXXXXXXXXXXXXX')
                            ->helperText('From TikTok Events Manager > your pixel > Details.'),
                    ]),

                Section::make('Social profiles')
                    ->description('Feeds the sitewide Organization schema (sameAs) so search engines connect this site to your real profiles — a Knowledge Panel prerequisite.')
                    ->columns(2)
                    ->components([
                        TextInput::make('seo_social_facebook')->label('Facebook URL')->url(),
                        TextInput::make('seo_social_twitter')->label('X / Twitter URL')->url(),
                        TextInput::make('seo_social_linkedin')->label('LinkedIn URL')->url(),
                        TextInput::make('seo_social_instagram')->label('Instagram URL')->url(),
                        TextInput::make('seo_social_youtube')->label('YouTube URL')->url(),
                    ]),

                Section::make('robots.txt')
                    ->description('Served live at /robots.txt.')
                    ->components([
                        Textarea::make('seo_robots_txt')
                            ->label(false)
                            ->rows(6)
                            ->extraInputAttributes(['style' => 'font-family: monospace; font-size: 0.8rem;']),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SiteSetting::set($key, $value);
        }

        Notification::make()->title('SEO settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
