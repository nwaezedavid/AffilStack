<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\OriginalImageUpload;
use App\Models\SiteSetting;
use App\Services\Seo\OrganizationSchema;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * SEO > Organization schema — the built-in, editable Organization and
 * WebSite structured data output on every public page (see
 * OrganizationSchema). Prefilled from brand settings, so it is valid
 * before anyone edits it; this form just lets the owner make it complete
 * (legal name, founding date, address, phone, extra profiles).
 */
class OrganizationSchemaSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Organization schema';

    protected static ?string $title = 'Organization & website schema';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'organization-schema';

    protected string $view = 'filament.pages.seo-form-page';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Tells Google who is behind the site — the facts behind a brand Knowledge Panel, your logo in results, and your site name above every result. Output as JSON-LD on every public page.';
    }

    public function mount(): void
    {
        $settings = app(OrganizationSchema::class)->settings();
        $settings['logo_path'] = $settings['logo_path'] ?: null;

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Organization')
                    ->icon('heroicon-o-building-office-2')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->label('Name')->required()->maxLength(120)
                            ->helperText('The brand name people search for.'),
                        TextInput::make('alternate_name')->label('Alternate name')->maxLength(120)
                            ->helperText('Another name or abbreviation, e.g. "Affil Stack".'),
                        TextInput::make('legal_name')->label('Legal name')->maxLength(160)
                            ->helperText('The registered company name, if different (e.g. "AffilStack Technologies Ltd").'),
                        TextInput::make('url')->label('Website URL')->url()->required(),
                        Textarea::make('description')->label('Description')->rows(3)->maxLength(500)->columnSpanFull(),
                        DatePicker::make('founding_date')->label('Founding date')->native(false)->maxDate(now()),
                        OriginalImageUpload::make('logo_path')
                            ->label('Logo for search engines (optional)')
                            ->directory('seo')
                            ->helperText('A square PNG or JPG, at least 112×112 (512×512 recommended), that looks right on a white background. Blank = the 512×512 PNG generated from your site icon, which already meets Google\'s requirements.'),
                        Repeater::make('founders')
                            ->label('Founders')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(120),
                                TextInput::make('url')->label('Profile URL (optional)')->url(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add founder')
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact & address')
                    ->icon('heroicon-o-phone')
                    ->columns(2)
                    ->components([
                        TextInput::make('email')->email(),
                        TextInput::make('telephone')->tel()->placeholder('+234 ...')
                            ->helperText('International format with country code.'),
                        Select::make('contact_type')->label('Contact point type')->options(OrganizationSchema::CONTACT_TYPES)->default('customer support'),
                        TextInput::make('street_address')->label('Street address')->columnSpanFull(),
                        TextInput::make('address_locality')->label('City'),
                        TextInput::make('address_region')->label('State / region'),
                        TextInput::make('postal_code')->label('Postal code'),
                        TextInput::make('address_country')->label('Country code')->maxLength(2)->placeholder('NG')
                            ->helperText('Two-letter ISO code, e.g. NG, GB, US.'),
                    ]),

                Section::make('Profiles (sameAs)')
                    ->icon('heroicon-o-link')
                    ->description('Your social profiles from SEO > SEO settings are included automatically. Add any other official profiles here — Crunchbase, Wikipedia, Product Hunt, G2, Trustpilot.')
                    ->components([
                        Placeholder::make('social_profiles')
                            ->label('Included from SEO settings')
                            ->content(fn () => new HtmlString(e(implode(' · ', array_filter(array_map(fn ($key) => SiteSetting::get($key), OrganizationSchema::SOCIAL_KEYS)))) ?: 'None yet — add them under SEO > SEO settings > Social profiles.')),
                        TagsInput::make('extra_same_as')
                            ->label('Other profile URLs')
                            ->placeholder('https://... then Enter')
                            ->nestedRecursiveRules(['url']),
                    ]),

                Section::make('Website')
                    ->icon('heroicon-o-globe-alt')
                    ->description('Google uses this (with your homepage title) to choose the site name shown above every search result. No search box is declared because the site has no search results page.')
                    ->columns(2)
                    ->components([
                        TextInput::make('website_name')->label('Site name')->required()->maxLength(80),
                        TextInput::make('website_alternate_name')->label('Alternate site name')->maxLength(80)
                            ->helperText('e.g. "AffilStack.com" — a fallback Google may use.'),
                    ]),

                Section::make('Output preview')
                    ->icon('heroicon-o-code-bracket')
                    ->description('What is published on every page (after saving). Test it with Google\'s Rich Results Test.')
                    ->collapsed()
                    ->components([
                        Placeholder::make('json_ld_preview')
                            ->hiddenLabel()
                            ->content(fn () => new HtmlString('<pre style="white-space: pre-wrap; font-size: 12px; max-height: 420px; overflow: auto;">'.e(json_encode([
                                '@context' => 'https://schema.org',
                                '@graph' => [app(OrganizationSchema::class)->organizationNode(), app(OrganizationSchema::class)->websiteNode()],
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'</pre>')),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['founders'] = array_values($data['founders'] ?? []);
        $data['extra_same_as'] = array_values($data['extra_same_as'] ?? []);

        app(OrganizationSchema::class)->save($data);

        Notification::make()->title('Organization schema saved')->success()->send();
    }
}
