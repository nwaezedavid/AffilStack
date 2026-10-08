<?php

namespace App\Filament\Resources\ConsentRecords;

use App\Filament\Clusters\Analytics;
use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\ConsentRecords\Pages\ListConsentRecords;
use App\Models\ConsentRecord;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agent 04: the consent log — read-only proof of every cookie choice
 * (ConsentController). Append-only by design: no create, edit or delete
 * here; old records are removed only by the retention purge.
 */
class ConsentRecordResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $model = ConsentRecord::class;

    protected static ?string $cluster = Analytics::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Consent Log';

    protected static ?string $modelLabel = 'consent record';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, Y H:i')->sortable()->description(fn (ConsentRecord $record) => $record->created_at?->diffForHumans()),
                TextColumn::make('action')->label('Choice')->badge()
                    ->formatStateUsing(fn (string $state) => ConsentRecord::ACTIONS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'accept_all' => 'success',
                        'reject_all', 'withdraw' => 'danger',
                        'signal' => 'warning',
                        default => 'info',
                    }),
                IconColumn::make('analytics')->label('Analytics')->boolean(),
                IconColumn::make('marketing')->label('Advertising')->boolean(),
                TextColumn::make('source')->label('Where')->formatStateUsing(fn (string $state) => ucfirst($state))->toggleable(),
                TextColumn::make('policy_version')->label('Policy')->badge()->color('gray'),
                IconColumn::make('gpc')->label('GPC')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.email')->label('Account')->placeholder('Anonymous')->searchable(),
                TextColumn::make('browser')->label('Browser')->description(fn (ConsentRecord $record) => $record->device_type)->toggleable(),
                TextColumn::make('page_path')->label('Page')->formatStateUsing(fn (?string $state) => $state === '/' ? '/' : '/'.ltrim((string) $state, '/'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_id')->label('Consent id')->limit(8)->tooltip(fn (ConsentRecord $record) => $record->consent_id)->fontFamily('mono')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_hash')->label('IP (hashed)')->limit(12)->fontFamily('mono')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('Choice')->options(ConsentRecord::ACTIONS),
                TernaryFilter::make('analytics')->label('Allowed analytics'),
                TernaryFilter::make('marketing')->label('Allowed advertising'),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConsentRecords::route('/'),
        ];
    }
}
