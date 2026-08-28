<?php

namespace App\Filament\Resources\Users\Schemas;

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
                        TextInput::make('company_name'),
                        TextInput::make('country'),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (?string $state) => Hash::make($state))
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Leave blank to keep the current password.'),
                    ]),

                Section::make('Access & credits')
                    ->columns(2)
                    ->components([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->options(fn () => Role::pluck('name', 'id'))
                            ->helperText('Only "admin" and "support" roles can access this dashboard.'),
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
