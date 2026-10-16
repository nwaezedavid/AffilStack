<?php

namespace App\Filament\Resources\SiteLanguages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\SiteLanguages\Pages\CreateSiteLanguage;
use App\Filament\Resources\SiteLanguages\Pages\EditSiteLanguage;
use App\Filament\Resources\SiteLanguages\Pages\ListSiteLanguages;
use App\Jobs\ScanSiteForTranslations;
use App\Models\SiteLanguage;
use App\Models\Translation;
use App\Support\Translation\TranslationStore;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Feature 6: the languages the website, dashboard and partner portal are
 * offered in. A new language starts as a draft while Claude translates;
 * publish it to add it to the switcher, give it /{code}/ addresses and
 * suggest it to matching visitors.
 */
class SiteLanguageResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static ?string $model = SiteLanguage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Languages';

    protected static ?string $modelLabel = 'language';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->components([
                Select::make('code')
                    ->label('Language')
                    ->options(collect(config('languages.available'))->mapWithKeys(fn ($language, $code) => [$code => $language['name'].' ('.$language['native'].')']))
                    ->searchable()
                    ->required()
                    ->disabledOn('edit')
                    ->unique(ignoreRecord: true)
                    ->rules([fn () => static::addressRule()])
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $language = config('languages.available.'.$state);

                        if ($language) {
                            $set('name', $language['name']);
                            $set('native_name', $language['native']);
                            $set('rtl', $language['rtl']);
                        }
                    })
                    ->helperText('Its code starts the website address, e.g. affilstack.com/fr/pricing.'),
                Select::make('status')
                    ->options(['draft' => 'Draft: translating, only admins see it', 'live' => 'Live: in the switcher and suggested to visitors'])
                    ->default('draft')
                    ->required()
                    ->selectablePlaceholder(false),
                TextInput::make('name')->label('Name in English')->required()->maxLength(60),
                TextInput::make('native_name')->label('Name in the language itself')->helperText('Shown in the language switcher.')->required()->maxLength(60),
                Toggle::make('rtl')->label('Written right to left')->helperText('Arabic, Hebrew, Persian and Urdu.'),
                TextInput::make('sort_order')->label('Order in the switcher')->numeric()->default(0),
            ]),
        ]);
    }

    /**
     * A language code can't be the first part of an existing address.
     */
    protected static function addressRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $taken = collect(app('router')->getRoutes())->map(fn ($route) => strtolower(explode('/', ltrim($route->uri(), '/'))[0]))->unique();

            if ($taken->contains(strtolower((string) $value))) {
                $fail('That code is already used by a page address on the site.');
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->description(fn (SiteLanguage $record) => $record->native_name)->searchable(),
                TextColumn::make('code')->badge()->color('gray'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => ucfirst($state))->color(fn (string $state) => $state === 'live' ? 'success' : 'warning'),
                TextColumn::make('progress')
                    ->label('Translated')
                    ->state(function (SiteLanguage $record) {
                        $counts = Translation::query()->where('locale', $record->code)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
                        $total = (int) $counts->sum();
                        $done = (int) ($counts['machine'] ?? 0) + (int) ($counts['reviewed'] ?? 0);

                        return $total === 0 ? 'Nothing found yet' : round($done / $total * 100).'% of '.number_format($total).' texts';
                    })
                    ->description(function (SiteLanguage $record) {
                        $pending = Translation::query()->where('locale', $record->code)->where('status', 'pending')->count();
                        $failed = Translation::query()->where('locale', $record->code)->where('status', 'failed')->count();

                        return trim(($pending ? number_format($pending).' waiting' : '').($failed ? ' · '.number_format($failed).' need a person' : ''), ' ·') ?: null;
                    }),
            ])
            ->recordActions([
                Action::make('translateNow')
                    ->label('Translate now')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->action(function (SiteLanguage $record) {
                        cache()->forget('translations:kick:'.$record->code);
                        TranslationStore::kick($record->code);
                        Notification::make()->title('Translating waiting '.$record->name.' texts in the background.')->success()->send();
                    }),
                Action::make('scan')
                    ->label('Scan website')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->action(function (SiteLanguage $record) {
                        ScanSiteForTranslations::dispatch([$record->code]);
                        Notification::make()->title('Scanning every public page in '.$record->name.'. New texts are translated as they\'re found.')->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteLanguages::route('/'),
            'create' => CreateSiteLanguage::route('/create'),
            'edit' => EditSiteLanguage::route('/{record}/edit'),
        ];
    }
}
