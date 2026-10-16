<?php

namespace App\Filament\Resources\AddonReviews;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\AddonReviews\Pages\ListAddonReviews;
use App\Filament\Resources\AddonReviews\Pages\ViewAddonReview;
use App\Models\AddonVersion;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Feature 7: every add-on version developers submit, to approve before
 * customers see it or send back with a note.
 */
class AddonReviewResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'marketplace';

    protected static ?string $model = AddonVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Add-on reviews';

    protected static ?string $modelLabel = 'add-on version';

    protected static ?string $slug = 'addon-reviews';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $waiting = AddonVersion::where('status', 'submitted')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Add-on')->description(fn (AddonVersion $record) => $record->addon?->typeLabel().' · v'.$record->version)->searchable(),
                TextColumn::make('addon.developer.display_name')->label('Developer'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ['submitted' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Sent back', 'draft' => 'Draft', 'superseded' => 'Replaced'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('pricing')->label('Price')->formatStateUsing(fn ($state, AddonVersion $record) => $record->priceLabel()),
                TextColumn::make('submitted_at')->since()->sortable()->label('Submitted'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['submitted' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Sent back', 'superseded' => 'Replaced'])->default('submitted'),
            ])
            ->recordActions([ViewAction::make()->label('Review')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Listing')->columns(2)->components([
                TextEntry::make('name'),
                TextEntry::make('version'),
                TextEntry::make('addon.developer.display_name')->label('Developer')
                    ->helperText(fn (AddonVersion $record) => $record->addon?->developer?->support_email.' · account '.$record->addon?->developer?->user?->email),
                TextEntry::make('type')->state(fn (AddonVersion $record) => $record->addon?->typeLabel()),
                TextEntry::make('tagline')->columnSpanFull(),
                TextEntry::make('description')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
                TextEntry::make('category')->state(fn (AddonVersion $record) => $record->categoryLabel()),
                TextEntry::make('price')->state(fn (AddonVersion $record) => $record->priceLabel()),
                ImageEntry::make('icon_path')->label('Icon')->disk('public')->imageHeight(64),
                ImageEntry::make('screenshots')->disk('public')->imageHeight(140)->placeholder('None'),
                TextEntry::make('website_url')->label('Website')->url(fn ($state) => $state)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('support_url')->label('Support')->placeholder('—'),
                TextEntry::make('changelog')->label('Notes from the developer')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Connected app')->columns(2)->visible(fn (AddonVersion $record) => $record->addon?->isApp())->components([
                TextEntry::make('install_url')->label('Connect page')->url(fn ($state) => $state)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('privacy_url')->label('Privacy policy')->url(fn ($state) => $state)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('scopes')->label('Permissions asked for')->badge()->placeholder('None'),
                TextEntry::make('redirect_uris')->label('Redirect URLs')->listWithLineBreaks()->placeholder('None'),
                TextEntry::make('webhook_url')->placeholder('—'),
                TextEntry::make('webhook_events')->badge()->placeholder('None'),
            ]),
            Section::make('Templates')->visible(fn (AddonVersion $record) => $record->addon?->type === 'template_pack')->components([
                TextEntry::make('templates')->hiddenLabel()->listWithLineBreaks()
                    ->state(fn (AddonVersion $record) => collect($record->content['items'] ?? [])->map(fn ($item) => '['.($item['kind'] ?? '').'] '.($item['title'] ?? '').' — '.Str::limit((string) ($item['body'] ?? ''), 160))->all()),
            ]),
            Section::make('Automation recipe')->columns(2)->visible(fn (AddonVersion $record) => $record->addon?->type === 'automation')->components([
                TextEntry::make('platform')->state(fn (AddonVersion $record) => config('marketplace.automation_platforms.'.($record->content['platform'] ?? ''), '—')),
                TextEntry::make('share_url')->label('Template link')->state(fn (AddonVersion $record) => $record->content['share_url'] ?? null)->url(fn ($state) => $state)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('blueprint')->state(fn (AddonVersion $record) => empty($record->content['blueprint']) ? null : ($record->content['blueprint_name'] ?? 'blueprint.json').' ('.number_format(strlen($record->content['blueprint']) / 1024, 1).' KB)')->placeholder('None'),
                TextEntry::make('steps')->label('Setup steps')->state(fn (AddonVersion $record) => $record->content['steps'] ?? null)->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
            ]),
            Section::make('Review')->columns(2)->components([
                TextEntry::make('status'),
                TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
                TextEntry::make('review_note')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddonReviews::route('/'),
            'view' => ViewAddonReview::route('/{record}'),
        ];
    }
}
