<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\FeaturePages\FeaturePageResource;
use App\Models\FeaturePage;
use App\Models\SiteSetting;
use App\Support\Website\NavigationMenu;
use App\Support\Website\SiteCopy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Website → Navigation menu: every label in the header (Tools, Resources,
 * Pricing, Talk To An Expert, Login, Get Started/Dashboard), the Tools
 * mega-menu's column names and promo, the Resources dropdown's items and
 * order, and the footer's columns and links. See NavigationMenu.
 */
class NavigationMenuEditor extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Navigation menu';

    protected static ?string $title = 'Navigation menu (header & footer)';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.website-copy-page';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $values = [];

        foreach (array_keys(NavigationMenu::labelDefaults()) as $key) {
            $values[$key] = NavigationMenu::label($key);
        }

        $values['nav_resources'] = NavigationMenu::storedList('nav_resources') ?? NavigationMenu::defaultResources();
        $values['nav_footer'] = NavigationMenu::storedList('nav_footer') ?? NavigationMenu::defaultFooter();

        $this->form->fill($values);
    }

    public function publicUrl(): string
    {
        return route('home');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make('Menus')->persistTabInQueryString()->tabs([
                Tab::make('1. Header')
                    ->icon(Heroicon::OutlinedWindow)
                    ->components([
                        Callout::make('Desktop: logo on the left · Tools, Resources, Pricing in the centre · Talk To An Expert, Login and Get Started on the right. Login hides itself once someone is signed in, and Get Started becomes Dashboard. On phones and tablets the same items open in a slide-in menu.')->info(),
                        Section::make('Labels')
                            ->columns(3)
                            ->components([
                                TextInput::make('nav_tools_label')->label('Tools menu')->required()->maxLength(30),
                                TextInput::make('nav_resources_label')->label('Resources menu')->required()->maxLength(30),
                                TextInput::make('nav_pricing_label')->label('Pricing link')->required()->maxLength(30),
                                TextInput::make('nav_expert_label')->label('Talk To An Expert link')->required()->maxLength(40)->helperText('Goes to Contact for visitors, Support for customers.'),
                                TextInput::make('nav_login_label')->label('Login link')->required()->maxLength(30),
                                TextInput::make('nav_get_started_label')->label('Get Started button')->required()->maxLength(30)->helperText('Goes to Pricing → pay → account.'),
                                TextInput::make('nav_dashboard_label')->label('Button when signed in')->required()->maxLength(30),
                            ]),
                    ]),
                Tab::make('2. Tools mega-menu')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->components([
                        Callout::make('Each tool in this menu is a feature page — its name, icon, one-line description and column are edited under Website → Features (tool pages).')
                            ->info()
                            ->footerActions([Action::make('tools')->label('Edit the tools')->url(FeaturePageResource::getUrl())->color('gray')]),
                        Section::make('Column headings')
                            ->columns(2)
                            ->components(array_map(
                                fn (string $key): TextInput => TextInput::make('nav_group_'.$key.'_label')->label('Column: '.FeaturePage::GROUPS[$key])->required()->maxLength(40),
                                array_keys(FeaturePage::GROUPS),
                            )),
                        Section::make('Navy panel on the right of the menu')
                            ->columns(2)
                            ->components([
                                TextInput::make('nav_tools_promo_eyebrow')->label('Small label')->maxLength(30),
                                TextInput::make('nav_tools_promo_label')->label('Button (to How it works)')->maxLength(30),
                                TextInput::make('nav_tools_promo_heading')->label('Heading')->maxLength(100)->columnSpanFull(),
                                TextInput::make('nav_all_features_label')->label('"All features" link')->required()->maxLength(30),
                                TextInput::make('nav_all_features_description')->label('Line under it')->maxLength(80),
                            ]),
                    ]),
                Tab::make('3. Resources dropdown')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->components([
                        Callout::make('Drag items into any order and switch them on or off. Blog only shows once a blog address is set (Website → Homepage → Blog), and Affiliate Program only while the programme is switched on.')->info(),
                        Repeater::make('nav_resources')
                            ->hiddenLabel()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->addActionLabel('Add a link')
                            ->itemLabel(fn (array $state): string => ($state['label'] ?? '').(($state['visible'] ?? true) ? '' : ' — hidden'))
                            ->columns(2)
                            ->schema($this->linkSchema(withDescription: true)),
                    ]),
                Tab::make('4. Footer')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->components([
                        Section::make('Words')
                            ->columns(2)
                            ->components([
                                TextInput::make('footer_tagline')->label('Line under the logo')->maxLength(240)->columnSpanFull(),
                                TextInput::make('footer_bottom_note')->label('Small line at the very bottom (right)')->maxLength(160)->columnSpanFull(),
                            ]),
                        Repeater::make('nav_footer')
                            ->label('Link columns')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->maxItems(5)
                            ->addActionLabel('Add a column')
                            ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)
                            ->schema([
                                TextInput::make('heading')->label('Column heading')->required()->maxLength(30),
                                Repeater::make('links')
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Add a link')
                                    ->itemLabel(fn (array $state): string => ($state['label'] ?? '').(($state['visible'] ?? true) ? '' : ' — hidden'))
                                    ->columns(2)
                                    ->schema($this->linkSchema(withDescription: false)),
                            ]),
                    ]),
            ]),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function linkSchema(bool $withDescription): array
    {
        return array_values(array_filter([
            Select::make('key')->label('Goes to')->options(NavigationMenu::destinationOptions())->required()->live()->selectablePlaceholder(false),
            TextInput::make('label')->required()->maxLength(40),
            $withDescription ? TextInput::make('description')->label('One-line description')->maxLength(80)->columnSpanFull() : null,
            TextInput::make('url')
                ->label('Address')
                ->placeholder('https://… or /page')
                ->maxLength(255)
                ->regex('#^(/(?![/\\\\])|https?://|\#[A-Za-z0-9_-])#i')
                ->visible(fn (Get $get): bool => $get('key') === 'custom')
                ->required(fn (Get $get): bool => $get('key') === 'custom'),
            Toggle::make('visible')->label('Show')->default(true),
        ]));
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteCopy::save(array_intersect_key($data, NavigationMenu::labelDefaults()), NavigationMenu::labelDefaults());

        $clean = fn (array $row): array => array_filter([
            'key' => (string) ($row['key'] ?? ''),
            'label' => trim((string) ($row['label'] ?? '')),
            'description' => isset($row['description']) ? trim((string) $row['description']) : null,
            'url' => ($row['key'] ?? '') === 'custom' ? trim((string) ($row['url'] ?? '')) : null,
            'visible' => (bool) ($row['visible'] ?? true),
        ], fn ($value): bool => $value !== null);

        SiteSetting::set('nav_resources', json_encode(array_values(array_map($clean, $data['nav_resources'] ?? []))));
        SiteSetting::set('nav_footer', json_encode(array_values(array_map(
            fn (array $column): array => ['heading' => trim((string) ($column['heading'] ?? '')), 'links' => array_values(array_map($clean, $column['links'] ?? []))],
            $data['nav_footer'] ?? [],
        ))));

        Notification::make()->title('Menus saved')->body('The header and footer on every public page now use these.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')
                ->label('View the site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('home').'?preview='.now()->timestamp)
                ->openUrlInNewTab(),
        ];
    }
}
