<?php

namespace App\Filament\Resources\AdminSubAccounts\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminSubAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('departments')
                    ->label('Departments')
                    ->state(fn (User $record) => collect($record->getAllPermissions())
                        ->pluck('name')
                        ->map(fn (string $name) => data_get(config('admin.departments'), str($name)->after('department.')->toString(), $name))
                        ->implode(', ') ?: 'None')
                    ->wrap(),
                IconColumn::make('is_suspended')
                    ->label('Suspended')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
