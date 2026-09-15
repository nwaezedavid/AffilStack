<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
            'seo_blog_url' => SiteSetting::get('seo_blog_url'),
            'seo_robots_txt' => SiteSetting::get('seo_robots_txt', "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')),
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
                        FileUpload::make('seo_og_image_path')
                            ->label('Default social share image (OG image)')
                            ->image()
                            ->disk('public')
                            ->directory('seo')
                            ->helperText('Shown when a page is shared on social media or messaging apps. Recommended 1200×630.'),
                    ]),

                Section::make('Verification & analytics')
                    ->columns(2)
                    ->components([
                        TextInput::make('seo_ga_id')
                            ->label('Google Analytics measurement ID')
                            ->placeholder('G-XXXXXXXXXX'),
                        TextInput::make('seo_gsc_verification')
                            ->label('Google Search Console verification code')
                            ->helperText('The "content" value from Google\'s HTML tag verification method — paste just the code, not the full tag.'),
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
