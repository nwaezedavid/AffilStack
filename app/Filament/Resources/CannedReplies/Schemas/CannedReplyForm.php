<?php

namespace App\Filament\Resources\CannedReplies\Schemas;

use App\Models\CannedReply;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CannedReplyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->helperText('Shown in the picker when staff reply to a ticket.'),
                Textarea::make('body')->required()->rows(6),
                Select::make('status')
                    ->options([
                        CannedReply::STATUS_ACTIVE => 'Active — available in the reply picker',
                        CannedReply::STATUS_SUGGESTED => 'Suggested — awaiting review',
                        CannedReply::STATUS_ARCHIVED => 'Archived — hidden',
                    ])
                    ->required()
                    ->default(CannedReply::STATUS_ACTIVE)
                    ->helperText('Activate a suggestion to make it available, or archive one that no longer fits.'),
                Placeholder::make('ai_rationale')
                    ->label('Why Sam suggested this')
                    ->content(fn (?CannedReply $record) => $record?->ai_rationale)
                    ->visible(fn (?CannedReply $record) => (bool) $record?->ai_rationale),
                Placeholder::make('usage_summary')
                    ->label('Usage')
                    ->content(fn (?CannedReply $record) => $record?->usage_count
                        ? "Used {$record->usage_count} time(s), last on {$record->last_used_at?->format('M j, Y')}."
                        : 'Not used yet.')
                    ->visible(fn (?CannedReply $record) => $record !== null),
            ]);
    }
}
