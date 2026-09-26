<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use App\Models\CannedReply;
use App\Models\SupportTicket;
use App\Services\Agents\SamAgentService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The ticket conversation. Customers reply from their dashboard; staff reply
 * here, optionally starting from a canned reply (picked from the admin's
 * saved library, then edited before sending — the picker itself is never
 * persisted, only the resulting message).
 */
class MessagesRelationManager extends RelationManager
{
    /**
     * Filament makes relation managers read-only on a View page by default,
     * which silently disabled the Reply action — staff had no way to answer
     * a ticket at all.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('canned_reply_id')
                ->label('Start from a canned reply (optional)')
                ->options(fn () => CannedReply::query()->active()->pluck('title', 'id'))
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function ($state, Set $set): void {
                    if (! $state) {
                        return;
                    }

                    $reply = CannedReply::find($state);

                    if ($reply) {
                        $set('message', $reply->body);
                        app(SamAgentService::class)->recordCannedReplyUsage($reply);
                    }
                }),
            Textarea::make('message')
                ->required()
                ->rows(6)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                IconColumn::make('is_staff')
                    ->label('From')
                    ->icon(fn (bool $state) => $state ? 'heroicon-o-lifebuoy' : 'heroicon-o-user')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray'),
                TextColumn::make('message')->wrap()->limit(200),
                TextColumn::make('created_at')->dateTime('M j, Y g:ia')->sortable(),
            ])
            ->defaultSort('created_at')
            ->headerActions([
                CreateAction::make()
                    ->label('Reply')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['is_staff'] = true;

                        return $data;
                    })
                    ->after(function (): void {
                        /** @var SupportTicket $ticket */
                        $ticket = $this->getOwnerRecord();
                        $ticket->update([
                            'status' => 'pending',
                            'last_reply_at' => now(),
                        ]);
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
