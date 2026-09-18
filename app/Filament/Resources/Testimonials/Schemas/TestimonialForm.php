<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Testimonial;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Testimonial')
                    ->columns(2)
                    ->components([
                        TextInput::make('author_name')->required(),
                        TextInput::make('author_role')
                            ->label('Role or company (optional)')
                            ->placeholder('e.g. Affiliate marketer, or "Founder, Acme Co."'),
                        Textarea::make('quote')->required()->rows(3)->columnSpanFull(),
                        FileUpload::make('avatar_path')
                            ->label('Photo (optional)')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('testimonials'),
                        Select::make('rating')
                            ->label('Rating (optional)')
                            ->options(['5' => '★★★★★', '4' => '★★★★☆', '3' => '★★★☆☆', '2' => '★★☆☆☆', '1' => '★☆☆☆☆'])
                            ->native(false),
                        TextInput::make('sort_order')->numeric()->default(0),
                        Toggle::make('is_published')
                            ->default(true)
                            ->helperText('Only counts toward the homepage once '.Testimonial::MINIMUM_TO_DISPLAY.' testimonials are published.'),
                    ]),
            ]);
    }
}
