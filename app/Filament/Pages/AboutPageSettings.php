<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\TeamMembers\TeamMemberResource;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
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
 * Everything an admin can change about the public /about page without a
 * code deploy (task #161). The old About page was just a SitePage row —
 * plain rich-text prose with no structure, identical in shape to the
 * Terms/Privacy legal pages. This gives it real, purpose-built sections
 * instead (mission, vision, goals, founder, who it's for, how it works,
 * what it does, a closing CTA), each independently editable here, and
 * rendered by a bespoke template — see resources/views/marketing/about.blade.php.
 *
 * The founder section is the one exception to "always show something":
 * it stays hidden on the live page until a real name is entered, the same
 * "never fabricate — hide until real content exists" rule Testimonials
 * and Brand Logos already follow, since inventing a founder bio would be
 * dishonest. Every other section ships with genuine, non-fabricated
 * default copy about the product (mirroring how the homepage's feature
 * grid falls back to a real description instead of going empty), so the
 * page never looks unfinished on a fresh install while staying fully
 * overridable here.
 *
 * The four list-shaped fields (goals, what we do, how we work, who we
 * serve) are Repeaters — SiteSetting is a plain string key-value store
 * (see its docblock), so each round-trips through JSON exactly like
 * BrandSettings' menu_items field already does.
 */
class AboutPageSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'About Page';

    protected static ?string $title = 'About Page';

    protected string $view = 'filament.pages.about-page-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * The four repeater fields — stored as JSON, unlike every other field
     * on this page which is a plain string.
     *
     * @var array<int, string>
     */
    protected static array $repeaterKeys = ['about_goals', 'about_what_we_do', 'about_how_we_works', 'about_who_we_serve'];

    public function mount(): void
    {
        $decode = fn ($raw) => is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        $this->form->fill([
            'about_hero_eyebrow' => SiteSetting::get('about_hero_eyebrow'),
            'about_hero_headline' => SiteSetting::get('about_hero_headline'),
            'about_hero_subheadline' => SiteSetting::get('about_hero_subheadline'),
            'about_hero_image' => SiteSetting::get('about_hero_image_path'),
            'about_hero_image_alt' => SiteSetting::get('about_hero_image_alt'),
            'about_story' => SiteSetting::get('about_story'),
            'about_story_image' => SiteSetting::get('about_story_image_path'),
            'about_story_image_alt' => SiteSetting::get('about_story_image_alt'),
            'about_mission' => SiteSetting::get('about_mission'),
            'about_vision' => SiteSetting::get('about_vision'),
            'about_goals' => $decode(SiteSetting::get('about_goals', '[]')),
            'about_what_we_do' => $decode(SiteSetting::get('about_what_we_do', '[]')),
            'about_how_we_works' => $decode(SiteSetting::get('about_how_we_works', '[]')),
            'about_who_we_serve' => $decode(SiteSetting::get('about_who_we_serve', '[]')),
            'about_founder_name' => SiteSetting::get('about_founder_name'),
            'about_founder_role' => SiteSetting::get('about_founder_role'),
            'about_founder_bio' => SiteSetting::get('about_founder_bio'),
            'about_founder_photo' => SiteSetting::get('about_founder_photo_path'),
            'about_team_heading' => SiteSetting::get('about_team_heading'),
            'about_team_subheading' => SiteSetting::get('about_team_subheading'),
            'about_cta_heading' => SiteSetting::get('about_cta_heading'),
            'about_cta_subtext' => SiteSetting::get('about_cta_subtext'),
            'about_cta_button_label' => SiteSetting::get('about_cta_button_label'),
            'about_cta_button_url' => SiteSetting::get('about_cta_button_url'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Hero')
                    ->description('The banner at the top of the page. Leave blank to use the default copy.')
                    ->columns(2)
                    ->components([
                        TextInput::make('about_hero_eyebrow')
                            ->label('Eyebrow (small label above the headline)')
                            ->placeholder('About us')
                            ->columnSpanFull(),
                        Textarea::make('about_hero_headline')->rows(2)->columnSpanFull(),
                        Textarea::make('about_hero_subheadline')->rows(2)->columnSpanFull(),
                        WebpFileUpload::make('about_hero_image')
                            ->label('Hero image')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->helperText('A 1600×1000 px (16:10) photo of the team at work, the founder speaking at an event, or a real workspace — warm, natural light and real people rather than stock. JPG or WebP under 1 MB. It sits on a dark navy background in a rounded frame. Left empty, a designed illustration is shown.')
                            ->columnSpanFull(),
                        TextInput::make('about_hero_image_alt')
                            ->label('Hero image description (alt text)')
                            ->maxLength(150)
                            ->placeholder('The AffilStack team planning a product launch in Lagos')
                            ->columnSpanFull(),
                    ]),

                Section::make('Our story')
                    ->columns(2)
                    ->components([
                        Textarea::make('about_story')
                            ->label('Story')
                            ->rows(6)
                            ->helperText('A few paragraphs about how and why the company started.')
                            ->columnSpanFull(),
                        WebpFileUpload::make('about_story_image')
                            ->label('Story image')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->helperText('A 1200×900 px (4:3) picture that tells the origin story: an early whiteboard sketch, the first office, or the founder working on the first version. JPG or WebP under 800 KB. Shown beside the story text.')
                            ->columnSpanFull(),
                        TextInput::make('about_story_image_alt')
                            ->label('Story image description (alt text)')
                            ->maxLength(150)
                            ->columnSpanFull(),
                    ]),

                Section::make('Mission & vision')
                    ->columns(2)
                    ->components([
                        Textarea::make('about_mission')->rows(3),
                        Textarea::make('about_vision')->rows(3),
                    ]),

                Section::make('Our goals')
                    ->components([
                        Repeater::make('about_goals')
                            ->label('Goals')
                            ->schema([
                                TextInput::make('icon')->label('Emoji icon (optional)')->maxLength(4),
                                TextInput::make('title')->required(),
                                Textarea::make('description')->rows(2)->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add a goal')
                            ->defaultItems(0)
                            ->reorderable(),
                    ]),

                Section::make('What we do')
                    ->components([
                        Repeater::make('about_what_we_do')
                            ->label('What we do')
                            ->schema([
                                TextInput::make('icon')->label('Emoji icon (optional)')->maxLength(4),
                                TextInput::make('title')->required(),
                                Textarea::make('description')->rows(2)->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add an item')
                            ->defaultItems(0)
                            ->reorderable(),
                    ]),

                Section::make('How we work')
                    ->description('Shown as numbered steps, in the order listed here.')
                    ->components([
                        Repeater::make('about_how_we_works')
                            ->label('Steps')
                            ->schema([
                                TextInput::make('title')->required(),
                                Textarea::make('description')->rows(2)->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add a step')
                            ->defaultItems(0)
                            ->reorderable(),
                    ]),

                Section::make('Who we serve')
                    ->components([
                        Repeater::make('about_who_we_serve')
                            ->label('Audiences')
                            ->schema([
                                TextInput::make('icon')->label('Emoji icon (optional)')->maxLength(4),
                                TextInput::make('title')->required(),
                                Textarea::make('description')->rows(2)->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add an audience')
                            ->defaultItems(0)
                            ->reorderable(),
                    ]),

                Section::make('Founder')
                    ->description('Left blank, the founder section is hidden from the live page rather than showing a placeholder person.')
                    ->columns(2)
                    ->components([
                        TextInput::make('about_founder_name')->label('Name'),
                        TextInput::make('about_founder_role')->label('Role / title')->placeholder('Founder & CEO'),
                        Textarea::make('about_founder_bio')->label('Bio')->rows(4)->columnSpanFull(),
                        WebpFileUpload::make('about_founder_photo')
                            ->label('Photo')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('about')
                            ->helperText('A square 800×800 px (1:1) headshot: head and shoulders, face centred, plain or softly blurred background. JPG, PNG or WebP under 1 MB.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Team')
                    ->description('Add your CEO, CFO, COO and the rest of the team under Site → Team Members. They appear in a grid below the founder; the section stays hidden until someone is added.')
                    ->columns(2)
                    ->components([
                        TextInput::make('about_team_heading')->label('Team heading')->placeholder('Meet the team')->maxLength(100),
                        TextInput::make('about_team_subheading')->label('Team subheading')->placeholder('The people building AffilStack and looking after our members every day.')->maxLength(200),
                        Actions::make([
                            Action::make('manageTeam')
                                ->label('Manage team members')
                                ->url(fn (): string => TeamMemberResource::getUrl())
                                ->color('gray'),
                        ])->columnSpanFull(),
                    ]),

                Section::make('Closing call to action')
                    ->description('Leave blank to use the default "see plans & pricing" call to action.')
                    ->columns(2)
                    ->components([
                        TextInput::make('about_cta_heading')->columnSpanFull(),
                        Textarea::make('about_cta_subtext')->rows(2)->columnSpanFull(),
                        TextInput::make('about_cta_button_label'),
                        TextInput::make('about_cta_button_url')->url()->placeholder('https://... or /pricing'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $keys = [
            'about_hero_eyebrow', 'about_hero_headline', 'about_hero_subheadline', 'about_hero_image',
            'about_story', 'about_story_image', 'about_mission', 'about_vision',
            'about_goals', 'about_what_we_do', 'about_how_we_works', 'about_who_we_serve',
            'about_founder_name', 'about_founder_role', 'about_founder_bio', 'about_founder_photo',
            'about_hero_image_alt', 'about_story_image_alt', 'about_team_heading', 'about_team_subheading',
            'about_cta_heading', 'about_cta_subtext', 'about_cta_button_label', 'about_cta_button_url',
        ];

        foreach ($keys as $key) {
            $settingKey = match ($key) {
                'about_hero_image' => 'about_hero_image_path',
                'about_story_image' => 'about_story_image_path',
                'about_founder_photo' => 'about_founder_photo_path',
                default => $key,
            };

            $value = in_array($key, static::$repeaterKeys, true)
                ? json_encode($data[$key] ?? [])
                : ($data[$key] ?? null);

            SiteSetting::set($settingKey, $value);
        }

        Notification::make()->title('About page saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
