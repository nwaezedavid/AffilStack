<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Website\WebsiteCopyPage;
use App\Filament\Resources\Plans\PlanResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Website → Pricing page: every word on /pricing around the plans. Plan
 * names, prices, credits and what's included come from Billing → Plans.
 */
class PricingPageEditor extends WebsiteCopyPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Pricing page';

    protected static ?string $title = 'Pricing page';

    protected static ?int $navigationSort = 5;

    protected static function keys(): array
    {
        return [
            'pricing_eyebrow', 'pricing_heading', 'pricing_subheading', 'pricing_featured_label', 'pricing_button_label', 'pricing_details_label',
            'pricing_guarantee', 'pricing_footnote', 'pricing_faq_heading', 'pricing_faq_text',
        ];
    }

    public function publicUrl(): string
    {
        return route('registration.pricing');
    }

    protected function fields(): array
    {
        return [
            Callout::make('Plan names, prices, credits, contacts, seats and channels are edited under Billing → Plans, so they always match what customers pay.')
                ->info()
                ->footerActions([Action::make('plans')->label('Edit plans')->url(PlanResource::getUrl())->color('gray')]),
            Section::make('Top of the page')
                ->description('Wrap words in *asterisks* to colour them gold.')
                ->columns(2)
                ->components([
                    TextInput::make('pricing_eyebrow')->label('Small label')->maxLength(40),
                    TextInput::make('pricing_heading')->label('Headline')->required()->maxLength(140),
                    Textarea::make('pricing_subheading')->label('Subheadline')->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('pricing_guarantee')->label('Guarantee line under the headline')->maxLength(120)->columnSpanFull(),
                ]),
            Section::make('Plan cards')
                ->columns(3)
                ->components([
                    TextInput::make('pricing_featured_label')->label('Badge on the featured plan')->maxLength(30),
                    TextInput::make('pricing_button_label')->label('Button on each plan')->maxLength(30),
                    TextInput::make('pricing_details_label')->label('"See everything included" link')->maxLength(40),
                ]),
            Section::make('Bottom of the page')
                ->columns(2)
                ->components([
                    Textarea::make('pricing_footnote')->label('Payment note (a link to the refund policy is added after it)')->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('pricing_faq_heading')->label('Questions banner — heading')->maxLength(80),
                    TextInput::make('pricing_faq_text')->label('Questions banner — line')->maxLength(200),
                ]),
        ];
    }
}
