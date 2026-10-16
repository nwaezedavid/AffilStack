<?php

namespace App\Filament\Resources\SiteLanguages\Pages;

use App\Filament\Resources\SiteLanguages\SiteLanguageResource;
use App\Models\SiteSetting;
use App\Support\Translation\MachineTranslator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSiteLanguages extends ListRecords
{
    protected static string $resource = SiteLanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Translation settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->fillForm(fn () => [
                    'glossary' => SiteSetting::get(MachineTranslator::GLOSSARY_SETTING, ''),
                    'paused' => MachineTranslator::paused(),
                ])
                ->schema([
                    Textarea::make('glossary')
                        ->label('Glossary for Claude')
                        ->rows(8)
                        ->helperText('One rule per line, e.g. "Never translate: Product Hub" or "commission → commission (French)". Applies to new translations.'),
                    Toggle::make('paused')
                        ->label('Pause automatic translation')
                        ->helperText('New texts stay in English until you resume.'),
                ])
                ->action(function (array $data) {
                    SiteSetting::set(MachineTranslator::GLOSSARY_SETTING, trim((string) ($data['glossary'] ?? '')));
                    SiteSetting::setFlag(MachineTranslator::PAUSED_SETTING, (bool) ($data['paused'] ?? false));
                    Notification::make()->title('Translation settings saved')->success()->send();
                }),
            CreateAction::make()->label('Add language'),
        ];
    }
}
