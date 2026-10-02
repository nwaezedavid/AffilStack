<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Credits\CreditManager;
use App\Support\Lifecycle\UserActivity;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(['name', 'email', 'company_name'])
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('activity_status')
                    ->label('Activity')
                    ->badge()
                    ->state(fn (User $record) => UserActivity::statusFor($record))
                    ->formatStateUsing(fn (string $state) => UserActivity::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => UserActivity::COLORS[$state] ?? 'gray')
                    ->icon(fn (string $state) => match ($state) {
                        UserActivity::ACTIVE => 'heroicon-m-signal',
                        UserActivity::IDLE => 'heroicon-m-pause-circle',
                        UserActivity::INACTIVE => 'heroicon-m-moon',
                        default => 'heroicon-m-minus-circle',
                    }),
                TextColumn::make('last_active_at')
                    ->label('Last active')
                    ->since()
                    ->dateTimeTooltip('M j, Y H:i')
                    ->placeholder('Never')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw('COALESCE(last_active_at, last_login_at) '.($direction === 'asc' ? 'asc' : 'desc'))),
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
                TextColumn::make('deleted_at')
                    ->label('Deletion requested')
                    ->dateTime('M j, Y')
                    ->description(fn (User $record) => $record->trashed() ? 'Purged '.$record->deleted_at->addDays(30)->diffForHumans() : null)
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('activity')
                    ->label('Activity')
                    ->options(UserActivity::LABELS)
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null) ? UserActivity::scope($query, $data['value']) : $query),
                SelectFilter::make('roles')
                    ->relationship('roles', 'name'),
                TernaryFilter::make('is_suspended'),
                // Audit gap #4 — a self-deleted account is soft-deleted
                // immediately and only force-deleted 30 days later (see
                // PurgeDeletedAccounts), so this is how an admin finds one
                // to restore inside that window.
                TrashedFilter::make(),
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
                    // Never on your own account — a super-admin suspending
                    // themselves would lock the only approver out.
                    ->visible(fn (User $record) => ! $record->trashed() && $record->id !== auth()->id())
                    ->action(fn (User $record) => $record->update(['is_suspended' => ! $record->is_suspended])),
                // Audit gap #4 — the only way to undo a self-service account
                // deletion within its 30-day grace period (see
                // ProfileController::destroy() / PurgeDeletedAccounts).
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->visible(fn (User $record) => $record->trashed() && $record->id !== auth()->id()),
                EditAction::make()
                    ->visible(fn (User $record) => ! $record->trashed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
