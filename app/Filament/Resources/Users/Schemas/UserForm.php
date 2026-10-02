<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\Countries;
use App\Support\Lifecycle\LifecycleAudience;
use App\Support\Lifecycle\UserActivity;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required(),
                        TextInput::make('company_name')
                            ->label('Business name'),
                        Select::make('country')
                            ->options(Countries::options())
                            ->searchable()
                            ->formatStateUsing(fn (?string $state) => Countries::normalize($state) ?? $state),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (?string $state) => Hash::make($state))
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Leave blank to keep the current password.'),
                    ]),

                Section::make('Profile & activity')
                    ->description('What the customer has filled in on their Profile & Security page, plus their activity. Religion and gender are deliberately not shown — they are only used to pick festive greetings.')
                    ->columns(3)
                    ->hiddenOn('create')
                    ->components([
                        Placeholder::make('activity')
                            ->label('Activity')
                            ->content(fn (?User $record) => $record ? UserActivity::LABELS[UserActivity::statusFor($record)].' · last active '.(UserActivity::lastSeen($record)?->diffForHumans() ?? 'never') : '—'),
                        Placeholder::make('last_login')
                            ->label('Last sign-in')
                            ->content(fn (?User $record) => $record?->last_login_at?->format('M j, Y H:i') ?? 'Never'),
                        Placeholder::make('lifecycle_emails')
                            ->label('Birthday, holiday & tips emails')
                            ->content(fn (?User $record) => $record?->marketingEmailsEnabled() ? 'Subscribed' : 'Unsubscribed'),
                        Placeholder::make('birthday')
                            ->label('Birthday')
                            ->content(fn (?User $record) => $record?->birth_date ? $record->birth_date->format('j F') : 'Not set'),
                        Placeholder::make('phone_display')
                            ->label('Phone')
                            ->content(fn (?User $record) => $record?->phone ?: 'Not set'),
                        Placeholder::make('location')
                            ->label('Location & timezone')
                            ->content(fn (?User $record) => $record ? (collect([$record->city, $record->state_region, Countries::name(Countries::normalize($record->country))])->filter()->implode(', ') ?: 'Not set').' · '.LifecycleAudience::timezoneFor($record) : '—'),
                        Placeholder::make('website')
                            ->label('Website')
                            ->content(fn (?User $record) => $record?->business_website ?: 'Not set'),
                        Placeholder::make('industry')
                            ->label('Industry')
                            ->content(fn (?User $record) => $record?->business_industry ?: 'Not set'),
                    ]),

                Section::make('Access & credits')
                    ->columns(2)
                    ->components([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->options(fn () => Role::whereNotIn('name', ['admin_sub'])->pluck('name', 'id'))
                            ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
                            ->helperText('Only super-admin can change roles here. Admin sub-accounts (department-scoped staff) are created from Users & Access > Admin Sub-Accounts, not this form.'),
                        TextInput::make('credits_balance')
                            ->label('Credit balance')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->helperText('Adjust directly only for corrections/refunds — normal grants happen automatically via billing.'),
                        Toggle::make('is_suspended')
                            ->label('Account suspended')
                            ->helperText('A suspended user cannot log in or run new generations.'),
                    ]),
            ]);
    }
}
