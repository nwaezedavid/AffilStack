<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Website\WebsiteCopyPage;
use App\Filament\Resources\FeaturePages\FeaturePageResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Website → Features overview: the words on /features and the labels every
 * individual feature page shares. The tools themselves: Website → Features.
 */
class FeaturesOverviewEditor extends WebsiteCopyPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $navigationLabel = 'Features overview page';

    protected static ?string $title = 'Features overview page';

    protected static ?int $navigationSort = 3;

    protected static function keys(): array
    {
        return [
            'features_eyebrow', 'features_heading', 'features_subheading', 'features_primary_label', 'features_secondary_label', 'features_card_link_label',
            'features_cta_heading', 'features_cta_text', 'features_cta_label',
            'feature_primary_label', 'feature_secondary_label', 'feature_steps_heading', 'feature_faq_heading', 'feature_related_heading', 'feature_video_label',
        ];
    }

    public function publicUrl(): string
    {
        return route('features.index');
    }

    protected function fields(): array
    {
        return [
            Callout::make('The tools themselves — names, screenshots, videos, sections — are edited one by one under Website → Features (tool pages).')
                ->info()
                ->footerActions([Action::make('tools')->label('Edit the tool pages')->url(FeaturePageResource::getUrl())->color('gray')]),
            Section::make('/features — top of the page')
                ->description('Wrap words in *asterisks* to colour them gold.')
                ->columns(2)
                ->components([
                    TextInput::make('features_eyebrow')->label('Small label')->maxLength(80),
                    TextInput::make('features_heading')->label('Headline')->required()->maxLength(140),
                    Textarea::make('features_subheading')->label('Subheadline')->rows(3)->maxLength(400)->columnSpanFull(),
                    TextInput::make('features_primary_label')->label('Main button (to Pricing)')->maxLength(40),
                    TextInput::make('features_secondary_label')->label('Second button (Talk to an expert)')->maxLength(40),
                    TextInput::make('features_card_link_label')->label('Link on each tool card')->maxLength(30),
                ]),
            Section::make('/features — closing banner')
                ->columns(2)
                ->components([
                    TextInput::make('features_cta_heading')->label('Heading')->maxLength(120)->columnSpanFull(),
                    TextInput::make('features_cta_text')->label('Supporting line')->maxLength(200),
                    TextInput::make('features_cta_label')->label('Button text')->maxLength(40),
                ]),
            Section::make('Labels shared by every tool page')
                ->columns(2)
                ->components([
                    TextInput::make('feature_primary_label')->label('Main button in the page header')->maxLength(40),
                    TextInput::make('feature_secondary_label')->label('Second button (to /features)')->maxLength(40),
                    TextInput::make('feature_steps_heading')->label('"How to use it" heading')->maxLength(60),
                    TextInput::make('feature_faq_heading')->label('FAQ heading')->maxLength(80),
                    TextInput::make('feature_related_heading')->label('Related tools heading')->maxLength(60),
                    TextInput::make('feature_video_label')->label('Label above a section video')->maxLength(60),
                ]),
        ];
    }
}
