<?php

namespace App\Filament\Resources\Translations;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\Translations\Pages\EditTranslation;
use App\Filament\Resources\Translations\Pages\ListTranslations;
use App\Models\SiteLanguage;
use App\Models\Translation;
use App\Support\Translation\HtmlTranslator;
use App\Support\Translation\TranslationStore;
use BackedEnum;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Feature 6: every translated text, to check and correct. A text an admin
 * saves becomes "reviewed" and is never overwritten by Claude.
 */
class TranslationResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static ?string $model = Translation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Translations';

    protected static ?string $modelLabel = 'translation';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->components([
                TextEntry::make('source')
                    ->label('English')
                    ->helperText('Placeholders: <g1>…</g1> is a link or bold text, <x1/> an icon or line break, <v1/> a value that changes (a name, a number). Keep each one exactly once; move them where the grammar needs them.'),
                Textarea::make('translation')
                    ->label(fn (?Translation $record) => $record ? (SiteLanguage::where('code', $record->locale)->value('name') ?? strtoupper($record->locale)) : 'Translation')
                    ->rows(5)
                    ->required()
                    ->rules([fn (?Translation $record) => static::placeholderRule($record)]),
                TextEntry::make('context')->label('First seen on')->placeholder('—'),
            ]),
        ]);
    }

    protected static function placeholderRule(?Translation $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record) {
            if ($record && ! HtmlTranslator::placeholdersMatch($record->source, (string) $value)) {
                $fail('Keep exactly the same placeholders as the English (each <g…>, <x…/> and <v…/> once).');
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('locale')->label('Language')->badge()->color('gray'),
                TextColumn::make('source')->label('English')->limit(110)->wrap()->searchable(),
                TextColumn::make('translation')->limit(110)->wrap()->searchable()->placeholder('Waiting for Claude…'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'machine' => 'By Claude',
                        'reviewed' => 'Reviewed',
                        'failed' => 'Needs a person',
                        default => 'Waiting',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'reviewed' => 'success',
                        'machine' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('context')->label('First seen on')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->since()->label('Updated')->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')->label('Language')->options(fn () => SiteLanguage::query()->orderBy('sort_order')->pluck('name', 'code')->all()),
                SelectFilter::make('status')->options(['pending' => 'Waiting', 'machine' => 'By Claude', 'reviewed' => 'Reviewed', 'failed' => 'Needs a person']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retranslate')
                        ->label('Translate again with Claude')
                        ->icon('heroicon-o-sparkles')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            Translation::query()->whereKey($records->modelKeys())->update(['status' => 'pending', 'attempts' => 0]);

                            foreach ($records->pluck('locale')->unique() as $locale) {
                                cache()->forget('translations:kick:'.$locale);
                                TranslationStore::kick($locale);
                            }

                            Notification::make()->title($records->count().' texts queued for Claude.')->success()->send();
                        }),
                    BulkAction::make('approve')
                        ->label('Mark as reviewed')
                        ->icon('heroicon-o-check')
                        ->action(function (Collection $records) {
                            Translation::query()->whereKey($records->modelKeys())->where('status', 'machine')->update(['status' => 'reviewed']);
                            Notification::make()->title('Marked as reviewed.')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTranslations::route('/'),
            'edit' => EditTranslation::route('/{record}/edit'),
        ];
    }
}
