<?php

namespace App\Filament\Resources\LifecycleEmails\Tables;

use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Support\Countries;
use App\Support\Lifecycle\FestivalCalendar;
use App\Support\Lifecycle\LifecycleAudience;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class LifecycleEmailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(fn ($query) => $query->withCount(['logs as sent_count' => fn ($logs) => $logs->where('status', 'sent')]))
            ->columns([
                TextColumn::make('name')
                    ->label('Email')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (LifecycleEmail $record) => Str::limit($record->subject, 60)),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        LifecycleEmail::TYPE_BIRTHDAY => 'Birthday',
                        LifecycleEmail::TYPE_FESTIVE => 'Festive',
                        default => 'Re-engagement',
                    })
                    ->colors([
                        'warning' => LifecycleEmail::TYPE_BIRTHDAY,
                        'success' => LifecycleEmail::TYPE_FESTIVE,
                        'info' => LifecycleEmail::TYPE_REENGAGEMENT,
                    ]),
                TextColumn::make('audience')
                    ->label('Who gets it')
                    ->state(fn (LifecycleEmail $record) => self::audience($record))
                    ->wrap(),
                TextColumn::make('when')
                    ->label('When')
                    ->state(fn (LifecycleEmail $record) => self::when($record)),
                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->numeric()
                    ->sortable(),
                ToggleColumn::make('enabled')
                    ->label('On'),
            ])
            ->filters([
                SelectFilter::make('category')->options([
                    LifecycleEmail::CATEGORY_RELIGIOUS => 'Religious',
                    LifecycleEmail::CATEGORY_NATIONAL => 'National',
                    LifecycleEmail::CATEGORY_CULTURAL => 'Cultural',
                    LifecycleEmail::CATEGORY_UNIVERSAL => 'Universal',
                ]),
                TernaryFilter::make('enabled'),
            ])
            ->recordActions([
                Action::make('sendTest')
                    ->label('Send test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->action(function (LifecycleEmail $record): void {
                        $admin = auth()->user();

                        try {
                            Mail::to($admin)->send(new LifecycleMail($record, $admin, ['bonus_credits' => (int) $record->bonus_credits]));
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()->title('The test email could not be sent')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Test sent to '.$admin->email)->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    protected static function audience(LifecycleEmail $record): string
    {
        return match ($record->type) {
            LifecycleEmail::TYPE_BIRTHDAY => 'Everyone with a birthday saved',
            LifecycleEmail::TYPE_REENGAGEMENT => 'Customers inactive '.$record->inactive_days.'+ days',
            default => collect([
                $record->category === LifecycleEmail::CATEGORY_RELIGIOUS ? (LifecycleAudience::RELIGIONS[$record->religion] ?? $record->religion) : null,
                $record->countryCodes() ? collect($record->countryCodes())->map(fn ($code) => count($record->countryCodes()) > 3 ? $code : Countries::name($code))->implode(', ') : null,
            ])->filter()->implode(' · ') ?: 'Everyone',
        };
    }

    protected static function when(LifecycleEmail $record): string
    {
        if ($record->type !== LifecycleEmail::TYPE_FESTIVE) {
            return $record->type === LifecycleEmail::TYPE_BIRTHDAY ? 'On their birthday' : 'After '.$record->inactive_days.' quiet days';
        }

        $country = ($record->date_rule['type'] ?? null) === 'by_country' ? null : ($record->countryCodes()[0] ?? null);
        $next = FestivalCalendar::nextOccurrence($record, CarbonImmutable::today(), $country);

        return $next ? 'Next: '.$next->format('j M Y') : 'No upcoming date';
    }
}
