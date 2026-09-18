<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
            'hero_headline' => SiteSetting::get('hero_headline'),
            'hero_subheadline' => SiteSetting::get('hero_subheadline'),
            'hero_media_type' => SiteSetting::get('hero_media_type', 'none'),
            'hero_image' => SiteSetting::get('hero_image_path'),
            'hero_youtube_url' => SiteSetting::get('hero_youtube_url'),
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
                        WebpFileUpload::make('logo_square')
                            ->label('Logo (square / icon)')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->helperText('Square or circular mark — used as the browser tab icon (favicon), the home-screen icon on mobile, and anywhere else a square logo fits better than the wide one. PNG, at least 512×512.'),
                    ]),

                Section::make('Brand colors')
                    ->columns(3)
                    ->components([
                        ColorPicker::make('color_primary')->label('Blue (primary)'),
                        ColorPicker::make('color_navy')->label('Navy'),
                        ColorPicker::make('color_gold')->label('Gold (accent)'),
                    ]),

                Section::make('Homepage hero')
                    ->description('The main banner at the top of the homepage. Leave the headline/subheadline blank to keep the default copy.')
                    ->columns(2)
                    ->components([
                        TextInput::make('hero_headline')->columnSpanFull(),
                        Textarea::make('hero_subheadline')->rows(2)->columnSpanFull(),
                        Select::make('hero_media_type')
                            ->label('Hero media')
                            ->options([
                                'none' => 'None',
                                'image' => 'Image',
                                'youtube' => 'YouTube video',
                            ])
                            ->default('none')
                            ->live()
                            ->columnSpanFull(),
                        WebpFileUpload::make('hero_image')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->visible(fn ($get) => $get('hero_media_type') === 'image')
                            ->columnSpanFull(),
                        TextInput::make('hero_youtube_url')
                            ->label('YouTube URL')
                            ->url()
                            ->placeholder('https://www.youtube.com/watch?v=...')
                            ->visible(fn ($get) => $get('hero_media_type') === 'youtube')
                            ->columnSpanFull(),
                    ]),

                Section::make('Navigation menu')
                    ->components([
                        Repeater::make('menu_items')
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('url')->required()->helperText('e.g. /pricing or https://...'),
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
            'site_name', 'logo_rectangular', 'logo_square', 'color_primary', 'color_navy',
            'color_gold', 'menu_items', 'header_announcement', 'support_email',
            'footer_text', 'hero_headline', 'hero_subheadline', 'hero_media_type',
            'hero_image', 'hero_youtube_url',
        ] as $key) {
            $settingKey = match ($key) {
                'logo_rectangular' => 'logo_rectangular_path',
                'logo_square' => 'logo_square_path',
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

        Notification::make()->title('Site settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
