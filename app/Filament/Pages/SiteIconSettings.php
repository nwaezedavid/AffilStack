<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\OriginalImageUpload;
use App\Models\PageSeo;
use App\Models\SiteSetting;
use App\Services\Seo\FaviconGenerator;
use App\Services\Seo\FaviconHealth;
use App\Services\Seo\SeoHeadBuilder;
use App\Support\Seo\RenderedPageSnapshot;
use App\Support\Seo\SeoImage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * SEO > Site icon — upload the square icon once and get the full favicon
 * set (favicon.ico, PNG sizes, Apple touch icon, web manifest) Google and
 * every browser look for, a preview of how the homepage appears in Google
 * with it, and a health check of everything the favicon depends on. Edits
 * the same setting as the square logo in Site > Branding.
 */
class SiteIconSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Site icon & search look';

    protected static ?string $title = 'Site icon & search appearance';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'site-icon';

    protected string $view = 'filament.pages.seo-form-page';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'The little icon next to your site name in Google results, browser tabs and phone home screens. Upload it once — every size is generated for you.';
    }

    public function mount(): void
    {
        $this->form->fill(['logo_square' => SiteSetting::get('logo_square_path')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Section::make('1. Upload your icon')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->description('A square PNG, 512×512 or larger, ideally a simple mark that stays readable at 16px. Transparent backgrounds are fine. Kept exactly as uploaded — never converted to WebP.')
                        ->schema([
                            OriginalImageUpload::make('logo_square')
                                ->label('Site icon (square logo)')
                                ->directory('branding')
                                ->imageEditor()
                                ->imageEditorAspectRatioOptions(['1:1'])
                                ->helperText('Saving rebuilds favicon.ico (16/32/48), 48/96/192/512px PNGs, the 180px Apple touch icon and site.webmanifest immediately.'),
                        ]),
                    Section::make('2. How your homepage looks in Google')
                        ->icon('heroicon-o-magnifying-glass')
                        ->description('Uses your homepage SEO title and description from SEO > Page SEO.')
                        ->schema([
                            Placeholder::make('google_preview')
                                ->hiddenLabel()
                                ->content(fn () => new HtmlString(view('filament.seo.search-appearance', $this->homepagePreview())->render())),
                        ]),
                ]),
                Section::make('Generated icon set')
                    ->icon('heroicon-o-squares-2x2')
                    ->description('Served from stable URLs on your own domain, exactly where browsers and Googlebot look.')
                    ->schema([
                        Placeholder::make('icon_set')
                            ->hiddenLabel()
                            ->content(fn () => new HtmlString(view('filament.seo.icon-set', ['favicons' => app(FaviconGenerator::class)])->render())),
                    ]),
                Section::make('Search-engine readiness')
                    ->icon('heroicon-o-shield-check')
                    ->description('Everything Google checks before it shows your icon next to your results.')
                    ->schema([
                        Placeholder::make('health')
                            ->hiddenLabel()
                            ->content(fn () => new HtmlString(view('filament.seo.health', ['checks' => app(FaviconHealth::class)->checks()])->render())),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        SiteSetting::set('logo_square_path', $data['logo_square'] ?? null);

        $this->regenerate();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label('Rebuild favicon set')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->regenerate()),
        ];
    }

    protected function regenerate(): void
    {
        try {
            $result = app(FaviconGenerator::class)->generate();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Could not build the favicon set')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($result['source'] === 'upload' ? 'Favicon set rebuilt from your icon' : 'Favicon set rebuilt with the default monogram')
            ->body($result['warnings'] ? implode(' ', $result['warnings']) : 'Google picks up the new icon the next time it crawls your homepage — request indexing in Search Console to speed it up.')
            ->{$result['warnings'] ? 'warning' : 'success'}()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function homepagePreview(): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $home = PageSeo::forRoute('home');
        $input = $home?->seoInput();
        $snapshot = RenderedPageSnapshot::get('home');
        $builder = app(SeoHeadBuilder::class);
        $title = $input?->seoTitle ? $builder->renderTitle($input->seoTitle, 'Homepage', $siteName) : ($snapshot['title'] ?? $siteName);
        $description = $input?->metaDescription ?: (($snapshot['description'] ?? '') ?: (string) SiteSetting::get('seo_meta_description', ''));
        $imagePath = $input?->ogImagePath ?: SiteSetting::get('seo_og_image_path');
        $image = $imagePath ? ['url' => (string) SeoImage::url($imagePath)] : null;

        return [
            'site_name' => $siteName,
            'url' => route('home'),
            'host' => (string) parse_url(route('home'), PHP_URL_HOST),
            'title' => $title,
            'description' => $description,
            'favicon' => app(FaviconGenerator::class)->url('favicon-96x96.png'),
            'robots' => $input?->robotsDirective(),
            'og' => ['title' => $input?->ogTitle ?: $title, 'description' => $input?->ogDescription ?: $description, 'image' => $image],
            'twitter' => ['title' => $input?->twitterTitle ?: ($input?->ogTitle ?: $title), 'description' => $description, 'image' => $image],
        ];
    }
}
