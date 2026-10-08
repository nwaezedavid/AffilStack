<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Website\WebsiteCopyPage;
use App\Filament\Resources\FaqItems\FaqItemResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Website → FAQ page: the words around the questions on /faq. The questions
 * themselves are Website → FAQ questions.
 */
class FaqPageEditor extends WebsiteCopyPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'FAQ page';

    protected static ?string $title = 'FAQ page';

    protected static ?int $navigationSort = 6;

    protected static function keys(): array
    {
        return ['faq_eyebrow', 'faq_heading', 'faq_subheading', 'faq_search_placeholder', 'faq_help_heading', 'faq_help_text', 'faq_help_label'];
    }

    public function publicUrl(): string
    {
        return route('faq.index');
    }

    protected function fields(): array
    {
        return [
            Callout::make('Add, edit, group or hide the questions themselves under Website → FAQ questions — they appear here, on the homepage FAQ teaser and in Google\'s FAQ results.')
                ->info()
                ->footerActions([Action::make('questions')->label('Edit the questions')->url(FaqItemResource::getUrl())->color('gray')]),
            Section::make('Top of the page')
                ->columns(2)
                ->components([
                    TextInput::make('faq_eyebrow')->label('Small label')->maxLength(40),
                    TextInput::make('faq_heading')->label('Headline')->required()->maxLength(120),
                    Textarea::make('faq_subheading')->label('Subheadline')->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('faq_search_placeholder')->label('Search box hint')->maxLength(60),
                ]),
            Section::make('"Still have a question?" banner at the bottom')
                ->columns(2)
                ->components([
                    TextInput::make('faq_help_heading')->label('Heading')->maxLength(80),
                    TextInput::make('faq_help_label')->label('Button text (to the Help Centre)')->maxLength(40),
                    TextInput::make('faq_help_text')->label('Supporting line')->maxLength(160)->columnSpanFull(),
                ]),
        ];
    }
}
