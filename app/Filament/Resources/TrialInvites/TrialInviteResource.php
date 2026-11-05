<?php

namespace App\Filament\Resources\TrialInvites;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Resources\TrialInvites\Pages\CreateTrialInvite;
use App\Filament\Resources\TrialInvites\Pages\ListTrialInvites;
use App\Models\Plan;
use App\Models\TrialInvite;
use BackedEnum;
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
 * Admin-only 7-day free trial invites. The trial is never offered on the
 * public site; this page is the only place one starts. See
 * TrialInviteService for what happens after the email goes out.
 */
class TrialInviteResource extends Resource
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static ?string $model = TrialInvite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Trial Invites';

    protected static ?string $modelLabel = 'trial invite';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Send a 7-day free trial invite')
                ->description('They get an email with a link that works once and expires in 7 days. To start the trial they create an account (or sign in) and save a card. One trial per email address.')
                ->columns(2)
                ->components([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->label('Email address')->email()->required()->maxLength(255),
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn (): array => Plan::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->required(),
                    Select::make('billing_cycle')
                        ->label('Billing after the trial')
                        ->options(['monthly' => 'Monthly', 'yearly' => 'Annual'])
                        ->default('monthly')
                        ->selectablePlaceholder(false)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('plan.name')->label('Plan'),
                TextColumn::make('billing_cycle')->label('Billing')->formatStateUsing(fn (string $state) => $state === 'yearly' ? 'Annual' : 'Monthly'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (TrialInvite $record) => $record->status === TrialInvite::STATUS_SENT && $record->expires_at->isPast() ? TrialInvite::STATUS_EXPIRED : $record->status)
                    ->formatStateUsing(fn (string $state) => TrialInvite::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        TrialInvite::STATUS_CONVERTED => 'success',
                        TrialInvite::STATUS_ACCEPTED => 'info',
                        TrialInvite::STATUS_CANCELLED, TrialInvite::STATUS_EXPIRED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('subscription.trial_ends_at')->label('Trial ends')->dateTime('M j, Y')->placeholder('—'),
                TextColumn::make('expires_at')->label('Link expires')->dateTime('M j, Y'),
                TextColumn::make('sentBy.name')->label('Sent by')->placeholder('—'),
                TextColumn::make('created_at')->label('Sent')->dateTime('M j, Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(TrialInvite::STATUS_LABELS),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrialInvites::route('/'),
            'create' => CreateTrialInvite::route('/create'),
        ];
    }
}
