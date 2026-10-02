<?php

namespace App\Filament\Resources\TeamMembers\Schemas;

use App\Filament\Support\WebpFileUpload;
use App\Models\TeamMember;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeamMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        $profileUrl = fn (TextInput $input, string $placeholder): TextInput => $input
            ->url()
            ->placeholder($placeholder)
            ->regex('#^https?://#i')
            ->validationMessages(['regex' => 'Use a full link starting with https://']);

        return $schema
            ->columns(3)
            ->components([
                Section::make('Who they are')
                    ->description('Shown as a card in the "Meet the team" grid on the About page, next to the founder.')
                    ->columns(2)
                    ->columnSpan(2)
                    ->components([
                        TextInput::make('name')->required()->maxLength(120),
                        TextInput::make('role')
                            ->label('Role / title')
                            ->required()
                            ->maxLength(120)
                            ->datalist(TeamMember::SUGGESTED_ROLES)
                            ->placeholder('e.g. Chief Financial Officer (CFO)')
                            ->helperText('Pick a suggestion or type any title.'),
                        Textarea::make('bio')
                            ->label('Short bio')
                            ->rows(4)
                            ->maxLength(600)
                            ->columnSpanFull()
                            ->helperText('Two or three sentences in the third person: what they lead at AffilStack and the experience they bring. Around 40–60 words reads best.'),
                    ]),

                Section::make('Display')
                    ->columnSpan(1)
                    ->components([
                        Toggle::make('is_visible')
                            ->label('Show on the About page')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first. You can also drag rows on the list page.'),
                    ]),

                Section::make('Photo')
                    ->columnSpan(2)
                    ->components([
                        WebpFileUpload::make('photo_path')
                            ->label('Headshot')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('team')
                            ->helperText('A square headshot, 800×800 px (1:1): head and shoulders, face centred, looking at the camera, with a plain or softly blurred background (light grey or brand navy looks best). JPG, PNG or WebP under 1 MB. It is shown inside a rounded frame, so keep a little space around the head. No photo yet? Leave it empty and their initials are shown instead.'),
                    ]),

                Section::make('Profile links (optional)')
                    ->columnSpan(1)
                    ->components([
                        $profileUrl(TextInput::make('linkedin_url')->label('LinkedIn'), 'https://www.linkedin.com/in/...'),
                        $profileUrl(TextInput::make('x_url')->label('X (Twitter)'), 'https://x.com/...'),
                        $profileUrl(TextInput::make('website_url')->label('Website'), 'https://...'),
                    ]),
            ]);
    }
}
