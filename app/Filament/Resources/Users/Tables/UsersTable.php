<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Credits\CreditManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (User $record) => $record->email),
                BadgeColumn::make('roles.name')
                    ->label('Role')
                    ->colors([
                        'danger' => 'admin',
                        'warning' => 'support',
                        'gray' => 'user',
                    ]),
                TextColumn::make('activeSubscription.plan.name')
                    ->label('Plan')
                    ->badge()
                    ->default('No plan'),
                TextColumn::make('credits_balance')
                    ->label('Credits')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('crm_contacts_count')
                    ->label('Contacts')
                    ->counts('crmContacts')
                    ->sortable(),
                IconColumn::make('is_suspended')
                    ->label('Suspended')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name'),
                TernaryFilter::make('is_suspended'),
            ])
            ->recordActions([
                Action::make('grantCredits')
                    ->label('Adjust credits')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Radio::make('direction')
                            ->options(['grant' => 'Grant credits', 'deduct' => 'Deduct credits'])
                            ->default('grant')
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data, CreditManager $credits): void {
                        if ($data['direction'] === 'grant') {
                            $credits->grant($record, (int) $data['amount'], 'admin_adjustment');
                        } else {
                            $credits->spend($record, (int) $data['amount'], 'admin_adjustment');
                        }

                        Notification::make()->title('Credit balance updated')->success()->send();
                    }),
                Action::make('toggleSuspend')
                    ->label(fn (User $record) => $record->is_suspended ? 'Unsuspend' : 'Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color(fn (User $record) => $record->is_suspended ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['is_suspended' => ! $record->is_suspended])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
