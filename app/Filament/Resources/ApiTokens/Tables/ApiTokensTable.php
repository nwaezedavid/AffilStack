<?php

namespace App\Filament\Resources\ApiTokens\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->email),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => $state === 'admin' ? 'danger' : 'gray'),
                // API roadmap items #2/#5 — platform-wide oversight of which
                // tokens are read-only or sandbox, alongside the existing
                // admin/user type badge above.
                TextColumn::make('scope')
                    ->badge()
                    ->color(fn (string $state) => $state === 'read_only' ? 'warning' : 'gray')
                    ->formatStateUsing(fn (string $state) => $state === 'read_only' ? 'Read-only' : 'Full access'),
                TextColumn::make('is_sandbox')
                    ->label('Sandbox')
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'info' : 'gray')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Sandbox' : 'Live'),
                TextColumn::make('last_used_at')->dateTime()->sortable()->placeholder('Never'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['user' => 'User', 'admin' => 'Admin']),
                SelectFilter::make('scope')->options(['full' => 'Full access', 'read_only' => 'Read-only']),
            ])
            ->recordActions([
                DeleteAction::make()->label('Revoke'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Revoke selected'),
                ]),
            ]);
    }
}
