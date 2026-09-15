<?php

namespace App\Filament\Resources\AdminSubAccounts\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class AdminSubAccountForm
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
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state) => Hash::make($state))
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Leave blank to keep the current password.'),
                        Toggle::make('is_suspended')
                            ->label('Account suspended')
                            ->helperText('A suspended sub-account cannot log into this dashboard at all.'),
                    ]),

                Section::make('Departments')
                    ->description('What this sub-account can see. Approving an AI agent\'s changes, publishing content, and launching ad spend always require super-admin, regardless of department access.')
                    ->components([
                        CheckboxList::make('departments')
                            ->hiddenLabel()
                            ->options(fn () => config('admin.departments'))
                            ->columns(1)
                            ->bulkToggleable()
                            ->required(),
                    ]),
            ]);
    }
}
