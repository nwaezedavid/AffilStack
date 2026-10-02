<?php

namespace App\Filament\Resources\LifecycleEmails\Schemas;

use App\Models\LifecycleEmail;
use App\Services\Lifecycle\LifecycleEmailPreview;
use App\Services\Lifecycle\LifecycleEmailRenderer;
use App\Support\Countries;
use App\Support\Lifecycle\FestivalCalendar;
use App\Support\Lifecycle\LifecycleAudience;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class LifecycleEmailForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 5])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 3])
                            ->schema([
                                self::contentSection(),
                                self::birthdaySection(),
                                self::festiveSection(),
                                self::reengagementSection(),
                                Section::make('Internal notes')
                                    ->collapsed()
                                    ->schema([
                                        Textarea::make('admin_notes')
                                            ->hiddenLabel()
                                            ->rows(3)
                                            ->helperText('Only admins see this.'),
                                    ]),
                            ]),
                        Section::make('Live preview')
                            ->description('Exactly what users will receive, personalised with your own account. Updates as you type.')
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Html::make(fn (Get $get, ?LifecycleEmail $record) => LifecycleEmailPreview::iframe(
                                    LifecycleEmailPreview::fromState([
                                        'type' => $get('type'),
                                        'name' => $get('name'),
                                        'subject' => $get('subject'),
                                        'preheader' => $get('preheader'),
                                        'heading' => $get('heading'),
                                        'body' => $get('body'),
                                        'cta_label' => $get('cta_label'),
                                        'cta_url' => $get('cta_url'),
                                        'theme_color' => $get('theme_color'),
                                        'bonus_credits' => $get('bonus_credits'),
                                        'hero_image_path' => $get('hero_image_path'),
                                    ], $record),
                                    auth()->user(),
                                )),
                            ]),
                    ]),
            ]);
    }

    protected static function contentSection(): Section
    {
        $placeholders = collect(LifecycleEmailRenderer::PLACEHOLDERS)
            ->map(fn (string $label, string $key) => "<code>{{$key}}</code> {$label}")
            ->implode(' · ');

        return Section::make('Email content')
            ->description('Write it like a note from a friend. Placeholders in curly braces are filled in for each user.')
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Campaign name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true),
                Toggle::make('enabled')
                    ->label('Sending on autopilot')
                    ->helperText('Switch off to pause this email without losing it.')
                    ->default(true)
                    ->inline(false),
                Select::make('type')
                    ->options([
                        LifecycleEmail::TYPE_BIRTHDAY => 'Birthday',
                        LifecycleEmail::TYPE_FESTIVE => 'Festive season',
                        LifecycleEmail::TYPE_REENGAGEMENT => 'Re-engagement step',
                    ])
                    ->required()
                    ->default(LifecycleEmail::TYPE_FESTIVE)
                    ->disabledOn('edit')
                    ->live(),
                ColorPicker::make('theme_color')
                    ->label('Accent colour')
                    ->default('#2452D9')
                    ->regex('/^#[0-9A-Fa-f]{6}$/')
                    ->helperText('Colours the header band.')
                    ->live(onBlur: true),
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->live(onBlur: true),
                TextInput::make('preheader')
                    ->helperText('The grey preview line inboxes show after the subject.')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->live(onBlur: true),
                TextInput::make('heading')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->live(onBlur: true),
                MarkdownEditor::make('body')
                    ->required()
                    ->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
                    ->live(debounce: 800)
                    ->helperText(new HtmlString('Placeholders: '.$placeholders.'.<br>Show a sentence only when a value exists with <code>{if:product_name}…{/if}</code>, or only when it doesn\'t with <code>{unless:product_name}…{/unless}</code>.')),
                TextInput::make('cta_label')
                    ->label('Button label')
                    ->helperText('Leave empty for no button.')
                    ->maxLength(80)
                    ->live(onBlur: true),
                TextInput::make('cta_url')
                    ->label('Button link')
                    ->placeholder('{dashboard_url}')
                    ->helperText('A full https:// link or a placeholder like {dashboard_url}.')
                    ->maxLength(255)
                    ->live(onBlur: true),
                FileUpload::make('hero_image_path')
                    ->label('Hero image (optional)')
                    ->image()
                    ->disk('public')
                    ->directory('lifecycle-emails')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif'])
                    ->maxSize(2048)
                    ->columnSpanFull()
                    ->live()
                    ->helperText(fn (?LifecycleEmail $record) => trim(($record?->image_hint ? 'What to make: '.$record->image_hint.' ' : '').'Use JPG or PNG (not WebP — Outlook can\'t show it), 1200×600, under 2 MB. Shown full-width under the header.')),
            ]);
    }

    protected static function birthdaySection(): Section
    {
        return Section::make('Birthday gift')
            ->visible(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_BIRTHDAY)
            ->schema([
                TextInput::make('bonus_credits')
                    ->label('Bonus credits to gift')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100000)
                    ->default(0)
                    ->live(onBlur: true)
                    ->helperText('Added to the user\'s balance on their birthday through the credit ledger (reason "birthday_gift"). 0 = no gift; the {if:bonus_credits} paragraph is then left out automatically. Team seats and affiliate-only accounts get the greeting without a gift.'),
                Placeholder::make('birthday_timing')
                    ->label('When it goes out')
                    ->content('Every hour the system checks who has a birthday today in their own timezone and sends at the send hour (Settings on the list page). 29 February birthdays are celebrated on 28 February in other years.'),
            ]);
    }

    protected static function festiveSection(): Section
    {
        return Section::make('Audience & date')
            ->visible(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_FESTIVE)
            ->columns(2)
            ->schema([
                Select::make('category')
                    ->options([
                        LifecycleEmail::CATEGORY_RELIGIOUS => 'Religious — only users of the chosen religion',
                        LifecycleEmail::CATEGORY_NATIONAL => 'National — only users in the chosen countries',
                        LifecycleEmail::CATEGORY_CULTURAL => 'Cultural — everyone (or the chosen countries)',
                        LifecycleEmail::CATEGORY_UNIVERSAL => 'Universal — everyone',
                    ])
                    ->default(LifecycleEmail::CATEGORY_UNIVERSAL)
                    ->required(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_FESTIVE)
                    ->live()
                    ->helperText('If two festivals fall on the same day, a user gets only one: religious beats national beats cultural beats universal.'),
                Select::make('religion')
                    ->options(collect(LifecycleAudience::RELIGIONS)->except(['none', 'prefer_not_to_say', 'other']))
                    ->visible(fn (Get $get) => $get('category') === LifecycleEmail::CATEGORY_RELIGIOUS)
                    ->required(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_FESTIVE && $get('category') === LifecycleEmail::CATEGORY_RELIGIOUS),
                Select::make('countries')
                    ->label('Only users in these countries')
                    ->multiple()
                    ->searchable()
                    ->options(Countries::options())
                    ->columnSpanFull()
                    ->required(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_FESTIVE && $get('category') === LifecycleEmail::CATEGORY_NATIONAL)
                    ->helperText('Leave empty to send to every country.'),
                Select::make('date_rule.type')
                    ->label('Date rule')
                    ->options(fn (?LifecycleEmail $record) => array_merge([
                        'fixed' => 'Same date every year',
                        'easter' => 'Relative to Easter Sunday',
                        'nth_weekday' => 'Nth weekday of a month (e.g. 4th Thursday of November)',
                        'table' => 'Date table (lunar calendars)',
                    ], (($record?->date_rule ?? [])['type'] ?? null) === 'by_country' ? ['by_country' => 'Different rule per country (preset)'] : []))
                    ->default('fixed')
                    ->required(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_FESTIVE)
                    ->live(),
                TextInput::make('days_before')
                    ->label('Send this many days early')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(30)
                    ->default(0)
                    ->helperText('0 = on the day itself.'),
                Select::make('date_rule.month')
                    ->label('Month')
                    ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => CarbonImmutable::create(2000, $m, 1)->format('F')]))
                    ->visible(fn (Get $get) => in_array($get('date_rule.type'), ['fixed', 'nth_weekday'], true)),
                TextInput::make('date_rule.day')
                    ->label('Day of month')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->visible(fn (Get $get) => $get('date_rule.type') === 'fixed'),
                Select::make('date_rule.weekday')
                    ->label('Weekday')
                    ->options([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'])
                    ->visible(fn (Get $get) => $get('date_rule.type') === 'nth_weekday'),
                Select::make('date_rule.n')
                    ->label('Which one')
                    ->options([1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', -1 => 'Last'])
                    ->visible(fn (Get $get) => $get('date_rule.type') === 'nth_weekday'),
                TextInput::make('date_rule.offset')
                    ->label('Days from Easter Sunday')
                    ->numeric()
                    ->minValue(-60)
                    ->maxValue(60)
                    ->helperText('-2 = Good Friday, 0 = Easter Sunday, -21 = Mothering Sunday.')
                    ->visible(fn (Get $get) => $get('date_rule.type') === 'easter'),
                TagsInput::make('date_rule.dates')
                    ->label('Dates (YYYY-MM-DD)')
                    ->placeholder('2036-01-01')
                    ->nestedRecursiveRules(['date_format:Y-m-d'])
                    ->columnSpanFull()
                    ->helperText('One entry per occurrence. Add next years\' dates here before the table runs out.')
                    ->visible(fn (Get $get) => $get('date_rule.type') === 'table'),
                Placeholder::make('by_country_rule')
                    ->label('Per-country rule')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('date_rule.type') === 'by_country')
                    ->content(fn (?LifecycleEmail $record) => self::describeByCountry($record)),
                Repeater::make('date_overrides')
                    ->label('Date overrides')
                    ->helperText('Moon sighting moved a date, or your community celebrates on a different day? Add the year and the correct date — it replaces the calculated date(s) for that year.')
                    ->schema([
                        TextInput::make('year')->numeric()->minValue(2020)->maxValue(2100)->required(),
                        DatePicker::make('date')->required()->native(false)->displayFormat('j M Y'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add an override')
                    ->columnSpanFull(),
                Placeholder::make('upcoming')
                    ->label('Coming up')
                    ->columnSpanFull()
                    ->content(fn (?LifecycleEmail $record) => self::describeUpcoming($record)),
            ]);
    }

    protected static function reengagementSection(): Section
    {
        return Section::make('When to send')
            ->visible(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_REENGAGEMENT)
            ->schema([
                TextInput::make('inactive_days')
                    ->label('Days of inactivity before this step')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->required(fn (Get $get) => $get('type') === LifecycleEmail::TYPE_REENGAGEMENT)
                    ->helperText('Each customer gets each step at most once per quiet spell. The moment they use the dashboard again, the sequence stops and starts over the next time they go quiet. Team seats, affiliate-only accounts, suspended accounts and anyone who turned off these emails are skipped.'),
            ]);
    }

    /**
     * Rebuilds date_rule from the form so only the keys the chosen rule
     * type uses are stored — and a preset per-country rule (which the form
     * can't edit field by field) is kept exactly as it was.
     *
     * @param  array<string, mixed>|null  $submitted
     * @param  array<string, mixed>|null  $original
     * @return array<string, mixed>|null
     */
    public static function normalizeDateRule(?array $submitted, ?array $original): ?array
    {
        $type = $submitted['type'] ?? null;

        return match ($type) {
            'fixed' => ['type' => 'fixed', 'month' => (int) ($submitted['month'] ?? 1), 'day' => (int) ($submitted['day'] ?? 1)],
            'easter' => ['type' => 'easter', 'offset' => (int) ($submitted['offset'] ?? 0)],
            'nth_weekday' => ['type' => 'nth_weekday', 'month' => (int) ($submitted['month'] ?? 1), 'weekday' => (int) ($submitted['weekday'] ?? 7), 'n' => (int) ($submitted['n'] ?? 1)],
            'table' => ['type' => 'table', 'dates' => collect($submitted['dates'] ?? [])->map(fn ($date) => trim((string) $date))->filter()->unique()->sort()->values()->all()],
            'by_country' => ($original['type'] ?? null) === 'by_country' ? $original : null,
            default => $original,
        };
    }

    protected static function describeUpcoming(?LifecycleEmail $record): HtmlString|string
    {
        if (! $record || $record->type !== LifecycleEmail::TYPE_FESTIVE) {
            return 'Save to see the upcoming dates.';
        }

        $today = CarbonImmutable::today();
        $countries = array_unique(array_merge([null], array_keys(($record->date_rule ?? [])['countries'] ?? [])));
        $lines = [];

        foreach ($countries as $country) {
            $dates = [];
            $cursor = $today;

            for ($i = 0; $i < 3; $i++) {
                $next = FestivalCalendar::nextOccurrence($record, $cursor, $country);

                if (! $next) {
                    break;
                }

                $dates[] = $next->format('D j M Y');
                $cursor = $next->addDay();
            }

            $label = $country ? Countries::name($country) : (count($countries) > 1 ? 'Everywhere else' : 'Next dates');
            $lines[] = '<strong>'.e($label).':</strong> '.($dates ? e(implode(' · ', $dates)) : 'none — add dates or an override');
        }

        $warning = ! FestivalCalendar::hasTableEntryFor($record, $today->year + 1)
            ? '<br><span style="color:#b45309;">The date table has no entry for '.($today->year + 1).' yet — add it above so this keeps sending.</span>'
            : '';

        return new HtmlString(implode('<br>', $lines).$warning);
    }

    protected static function describeByCountry(?LifecycleEmail $record): string
    {
        $rule = $record?->date_rule ?? [];
        $describe = function (array $rule): string {
            return match ($rule['type'] ?? null) {
                'easter' => ($rule['offset'] ?? 0) === 0 ? 'Easter Sunday' : abs((int) $rule['offset']).' days '.(($rule['offset'] ?? 0) < 0 ? 'before' : 'after').' Easter Sunday',
                'nth_weekday' => [1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', -1 => 'Last'][(int) ($rule['n'] ?? 1)].' '.CarbonImmutable::create(2024, 1, (int) $rule['weekday'])->format('l').' of '.CarbonImmutable::create(2000, (int) $rule['month'], 1)->format('F'),
                'fixed' => CarbonImmutable::create(2000, (int) $rule['month'], (int) $rule['day'])->format('j F'),
                default => 'Custom',
            };
        };

        $parts = ['Default: '.$describe($rule['default'] ?? [])];

        foreach ($rule['countries'] ?? [] as $code => $countryRule) {
            $parts[] = Countries::name($code).': '.$describe($countryRule);
        }

        return implode(' · ', $parts).'. (Fixed preset — use date overrides to adjust a particular year.)';
    }
}
