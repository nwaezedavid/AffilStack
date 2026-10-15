<?php

namespace App\Filament\Resources\BannerAds\Schemas;

use App\Filament\Support\WebpFileUpload;
use App\Models\BannerAd;
use App\Support\ReferralProgram;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerAdForm
{
    public static function configure(Schema $schema): Schema
    {
        $sizes = collect(BannerAd::SIZES)
            ->mapWithKeys(fn (string $size): array => [$size => str_replace('x', '×', $size).' — '.(config("affiliate_kit.sizes.{$size}.label") ?? '')])
            ->all();

        return $schema
            ->columns(2)
            ->components([
                Section::make('The ad')
                    ->description('What visitors on partners\' sites see. Short copy works best: banners are small.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->label('Internal name')
                            ->required()
                            ->maxLength(120)
                            ->placeholder('Black Friday 2026')
                            ->helperText('Only you see this.')
                            ->columnSpanFull(),
                        TextInput::make('headline')
                            ->required()
                            ->maxLength(80)
                            ->placeholder('Your whole affiliate business, one stack')
                            ->helperText('Aim for under 45 characters so it fits the small sizes.')
                            ->columnSpanFull(),
                        Textarea::make('message')
                            ->label('Ad message')
                            ->rows(2)
                            ->maxLength(160)
                            ->placeholder('Research offers, find buyers and launch AI campaigns in minutes.')
                            ->helperText('Shown on the larger sizes.')
                            ->columnSpanFull(),
                        TextInput::make('cta_label')
                            ->label('Button text')
                            ->required()
                            ->default('Get started')
                            ->maxLength(30),
                        Select::make('landing_path')
                            ->label('Clicks go to')
                            ->options(fn () => ReferralProgram::landingPages())
                            ->default('/pricing')
                            ->required()
                            ->helperText('The partner\'s referral is credited wherever the visitor lands.'),
                    ]),
                Section::make('Discount (optional)')
                    ->description('Leave empty when there\'s no offer running.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('discount_badge')
                            ->label('Badge')
                            ->maxLength(30)
                            ->placeholder('30% OFF'),
                        TextInput::make('discount_note')
                            ->label('Small print')
                            ->maxLength(80)
                            ->placeholder('Annual plans, this week only'),
                    ]),
                Section::make('Look')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('theme')
                            ->options(BannerAd::themes())
                            ->default('navy')
                            ->required()
                            ->helperText('Dark themes use your white logo (Brand Settings) when you have uploaded one.'),
                        WebpFileUpload::make('image_path')
                            ->label('Image (optional)')
                            ->image()
                            ->disk('public')
                            ->directory('banner-ads')
                            ->maxSize(4096)
                            ->helperText('A product screenshot or visual, shown on the 300×250 and taller sizes. Landscape works best.'),
                        CheckboxList::make('sizes')
                            ->label('Show at these sizes')
                            ->options($sizes)
                            ->columns(2)
                            ->helperText('Leave all unticked to show at every size.')
                            ->columnSpanFull(),
                    ]),
                Section::make('When it runs')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Toggle::make('is_active')->label('Switched on')->default(true)->columnSpanFull(),
                        DateTimePicker::make('starts_at')->label('Starts')->seconds(false)->helperText('Empty: straight away.'),
                        DateTimePicker::make('ends_at')->label('Ends')->seconds(false)->after('starts_at')->helperText('Empty: until you switch it off. With a discount, banners show "Ends <date>".'),
                        Select::make('weight')
                            ->label('Share of views')
                            ->options([1 => 'Low', 3 => 'Below normal', 5 => 'Normal', 8 => 'High', 10 => 'Highest'])
                            ->default(5)
                            ->required()
                            ->helperText('When several ads are live, higher shows more often.'),
                        TextInput::make('sort_order')->label('Order in the gallery')->numeric()->default(0),
                    ]),
            ]);
    }
}
