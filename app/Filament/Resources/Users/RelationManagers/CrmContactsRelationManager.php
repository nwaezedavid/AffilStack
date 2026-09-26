<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin oversight only — every contact this user has saved to their CRM.
 * No create/edit here; contacts are managed by the user in their own dashboard.
 */
class CrmContactsRelationManager extends RelationManager
{
    /**
     * A relation manager with no linked resource falls back to viewAny —
     * which, without a policy, lets anyone who can open a customer (the
     * users_access department) read and delete every CRM contact they own.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->canAccessDepartment('crm_oversight') ?? false;
    }

    protected static string $relationship = 'crmContacts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('company')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('source')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime('M j, Y')->sortable(),
            ])
            ->headerActions([])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
