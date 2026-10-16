<?php

namespace App\Filament\Resources\Developers;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\Developers\Pages\EditDeveloper;
use App\Filament\Resources\Developers\Pages\ListDevelopers;
use App\Models\DeveloperProfile;
use App\Support\Marketplace\MarketplaceSettings;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Feature 7: the customers registered as developers. Suspend one, or give
 * one a different revenue share.
 */
class DeveloperResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'marketplace';

    protected static ?string $model = DeveloperProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Developers';

    protected static ?string $modelLabel = 'developer';

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->components([
                TextInput::make('display_name')->required()->maxLength(80),
                TextInput::make('support_email')->email()->required(),
                TextInput::make('website')->url()->nullable(),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])->required()
                    ->helperText('A suspended developer can\'t sign in to the portal and their add-ons leave the marketplace.'),
                TextInput::make('revenue_share_percent')->label('Revenue share (%)')->numeric()->minValue(0)->maxValue(100)->nullable()
                    ->placeholder(fn () => (string) MarketplaceSettings::revenueSharePercent())
                    ->helperText('Leave blank to use the marketplace default. Applies to future payments.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('display_name')->label('Developer')->description(fn (DeveloperProfile $record) => $record->user?->email)->searchable(),
                TextColumn::make('addons_count')->counts('addons')->label('Add-ons'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('revenue_share_percent')->label('Share')->formatStateUsing(fn ($state) => $state.'%')->placeholder('Default'),
                TextColumn::make('created_at')->since()->label('Joined'),
            ])
            ->filters([SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevelopers::route('/'),
            'edit' => EditDeveloper::route('/{record}/edit'),
        ];
    }
}
