<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Website\WebsiteCopyPage;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Website → Help Centre: every word on /help. The support email address is
 * Site → Brand settings; popular questions come from Website → FAQ questions.
 */
class HelpCentreEditor extends WebsiteCopyPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?string $navigationLabel = 'Help Centre page';

    protected static ?string $title = 'Help Centre page';

    protected static ?int $navigationSort = 7;

    protected static function keys(): array
    {
        return [
            'help_eyebrow', 'help_heading', 'help_subheading',
            'help_chat_title', 'help_chat_text', 'help_ticket_title', 'help_ticket_text', 'help_email_title', 'help_email_text', 'help_signin_note',
            'help_resources_heading', 'help_faq_title', 'help_faq_text', 'help_learn_title', 'help_learn_text', 'help_features_title', 'help_features_text', 'help_contact_title', 'help_contact_text',
            'help_popular_heading',
        ];
    }

    public function publicUrl(): string
    {
        return route('help.index');
    }

    protected function fields(): array
    {
        $card = fn (string $title, string $text, string $label): array => [
            TextInput::make($title)->label($label.' — title')->maxLength(60),
            Textarea::make($text)->label($label.' — description')->rows(2)->maxLength(200),
        ];

        return [
            Section::make('Top of the page')
                ->description('Wrap words in *asterisks* to colour them gold.')
                ->columns(2)
                ->components([
                    TextInput::make('help_eyebrow')->label('Small label')->maxLength(40),
                    TextInput::make('help_heading')->label('Headline')->required()->maxLength(120),
                    Textarea::make('help_subheading')->label('Subheadline')->rows(2)->maxLength(300)->columnSpanFull(),
                ]),
            Section::make('The three support cards')
                ->columns(2)
                ->components([
                    ...$card('help_chat_title', 'help_chat_text', 'Chat card'),
                    ...$card('help_ticket_title', 'help_ticket_text', 'Ticket card'),
                    ...$card('help_email_title', 'help_email_text', 'Email card'),
                    TextInput::make('help_signin_note')->label('Small note under the cards')->maxLength(200)->columnSpanFull(),
                ]),
            Section::make('Guides & resources')
                ->columns(2)
                ->components([
                    TextInput::make('help_resources_heading')->label('Heading')->maxLength(60)->columnSpanFull(),
                    ...$card('help_faq_title', 'help_faq_text', 'FAQ link'),
                    ...$card('help_learn_title', 'help_learn_text', 'Learning Centre link'),
                    ...$card('help_features_title', 'help_features_text', 'Feature guides link'),
                    ...$card('help_contact_title', 'help_contact_text', 'Talk to an expert link'),
                    TextInput::make('help_popular_heading')->label('"Popular questions" heading')->maxLength(60)->columnSpanFull(),
                ]),
        ];
    }
}
