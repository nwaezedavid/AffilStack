<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\OriginalImageUpload;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use App\Services\Seo\FaviconGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Everything an admin can change about the public site's look and structure
 * without touching code: logo, favicon, brand colors, main menu, and footer.
 */
class BrandSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected string $view = 'filament.pages.brand-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_name' => SiteSetting::get('site_name', 'AffilStack'),
            'logo_rectangular' => SiteSetting::get('logo_rectangular_path'),
            'logo_square' => SiteSetting::get('logo_square_path'),
            'logo_rectangular_white' => SiteSetting::get('logo_rectangular_white_path'),
            'logo_square_white' => SiteSetting::get('logo_square_white_path'),
            'color_primary' => SiteSetting::get('color_primary', '#2452D9'),
            'color_navy' => SiteSetting::get('color_navy', '#0B1E3D'),
            'color_gold' => SiteSetting::get('color_gold', '#C9A24A'),
            // SiteSetting::get() is backed by Cache::rememberForever(), so
            // whichever caller first populates the cache for a given key
            // "locks in" the shape of the default it passed. The marketing
            // layout reads this same key with an array default, so this has
            // to tolerate either shape rather than assuming a string.
            'menu_items' => (function ($menuItemsRaw) {
                return is_array($menuItemsRaw) ? $menuItemsRaw : (json_decode((string) $menuItemsRaw, true) ?: []);
            })(SiteSetting::get('menu_items', '[]')),
            'header_announcement' => SiteSetting::get('header_announcement'),
            'support_email' => SiteSetting::get('support_email', 'support@affilstack.com'),
            'footer_text' => SiteSetting::get('footer_text', '© '.date('Y').' AffilStack.'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->components([
                        TextInput::make('site_name')->required(),
                        TextInput::make('support_email')
                            ->label('Support email')
                            ->email()
                            ->helperText('Where Contact Us submissions are sent, and shown to visitors on the Contact page.'),
                        TextInput::make('header_announcement')
                            ->label('Header announcement (optional)')
                            ->columnSpanFull()
                            ->helperText('A one-line banner shown at the top of every public page. Leave blank to hide it.'),
                        WebpFileUpload::make('logo_rectangular')
                            ->label('Logo (rectangular)')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->helperText('Wide/horizontal logo — used in the site header, the sign-in/sign-up pages, and the dashboard sidebar. SVG or PNG with a transparent background works best.'),
                        // Kept as uploaded (never WebP — Google's favicon
                        // crawler doesn't accept it); saving rebuilds the
                        // favicon set. See SEO > Site icon / FaviconGenerator.
                        OriginalImageUpload::make('logo_square')
                            ->label('Logo (square / icon)')
                            ->directory('branding')
                            ->helperText('Square mark — becomes the favicon in Google results and browser tabs, and the home-screen icon on phones (every size is generated automatically). PNG, at least 512×512. Preview it under SEO > Site icon.'),
                    ]),

                Section::make('White logo & icon (for dark backgrounds)')
                    ->description('Optional white versions used wherever the background is dark: the site footer, the customer dashboard sidebar, the admin panel in dark mode, and the Chrome extension\'s header. Without them, the normal logo is used. Use a transparent PNG (or WebP) — the previews below are shown on a dark background.')
                    ->extraAttributes(['class' => 'affil-dark-preview'])
                    ->columns(2)
                    ->components([
                        WebpFileUpload::make('logo_rectangular_white')
                            ->label('White logo (rectangular)')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->helperText('Wide logo in white, transparent background. Shown at 32px tall.'),
                        WebpFileUpload::make('logo_square_white')
                            ->label('White icon (square)')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->helperText('Square mark in white, transparent background, at least 256×256. Used next to the site name where the wide logo doesn\'t fit, and in the Chrome extension header.'),
                    ]),

                Section::make('Brand colors')
                    ->columns(3)
                    ->components([
                        ColorPicker::make('color_primary')->label('Blue (primary)'),
                        ColorPicker::make('color_navy')->label('Navy'),
                        ColorPicker::make('color_gold')->label('Gold (accent)'),
                    ]),

                // The homepage hero (and every other homepage section) is
                // edited in Content → Homepage — see HomepageEditor.
                Section::make('Homepage')
                    ->description('The hero headline, buttons, image/video and every other homepage section now live in one place: Content → Homepage.')
                    ->components([
                        Actions::make([
                            Action::make('openHomepageEditor')
                                ->label('Open the homepage editor')
                                ->url(fn (): string => HomepageEditor::getUrl())
                                ->color('gray'),
                        ]),
                    ]),

                Section::make('Navigation menu')
                    ->components([
                        Repeater::make('menu_items')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('url')->required()->helperText('e.g. /pricing or https://...')
                                    // Site-relative paths or http(s) only — never javascript:/data:.
                                    ->regex('#^(/(?![/\\\\])|https?://)#i')
                                    ->validationMessages(['regex' => 'Use a site path like /pricing or a full https:// link.']),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add menu item')
                            ->reorderable()
                            ->defaultItems(0),
                    ]),

                Section::make('Footer')
                    ->components([
                        Textarea::make('footer_text')
                            ->rows(2)
                            ->helperText('Plain text shown in the site footer.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ([
            'site_name', 'logo_rectangular', 'logo_square', 'logo_rectangular_white', 'logo_square_white', 'color_primary', 'color_navy',
            'color_gold', 'menu_items', 'header_announcement', 'support_email',
            'footer_text', 'hero_headline', 'hero_subheadline', 'hero_media_type',
            'hero_image', 'hero_youtube_url',
        ] as $key) {
            // Hero fields moved to the homepage editor — never overwrite them from here.
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $settingKey = match ($key) {
                'logo_rectangular' => 'logo_rectangular_path',
                'logo_square' => 'logo_square_path',
                'logo_rectangular_white' => 'logo_rectangular_white_path',
                'logo_square_white' => 'logo_square_white_path',
                'hero_image' => 'hero_image_path',
                default => $key,
            };

            // menu_items is the only array-valued field here — SiteSetting
            // is a plain string key-value store (see its docblock), so this
            // is the one key that needs to round-trip through JSON rather
            // than being handed to it raw.
            $value = $key === 'menu_items' ? json_encode($data[$key] ?? []) : ($data[$key] ?? null);

            SiteSetting::set($settingKey, $value);
        }

        // The site name, colours and square logo all feed the favicon set
        // and web manifest.
        try {
            app(FaviconGenerator::class)->generate();
        } catch (\Throwable $e) {
            report($e);
        }

        Notification::make()->title('Site settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
