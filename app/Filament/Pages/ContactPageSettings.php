<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Everything an admin can change about the public /contact page without a
 * code deploy: the page image, and the two extra "front door" addresses
 * (hello@/info@) shown alongside the existing support email (still edited
 * in Brand Settings > Identity, since it also drives who receives every
 * Contact Us submission — see ContactController/ContactMessageReceived).
 *
 * Social profile links shown on this page come from Site > SEO Settings >
 * Social profiles — one set of URLs, reused everywhere (see
 * resources/views/components/social-links.blade.php), rather than a
 * second copy of the same fields living here too.
 */
class ContactPageSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Contact Page';

    protected static ?string $title = 'Contact Page';

    protected string $view = 'filament.pages.contact-page-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'contact_image' => SiteSetting::get('contact_image_path'),
            'contact_email_hello' => SiteSetting::get('contact_email_hello', 'hello@affilstack.com'),
            'contact_email_info' => SiteSetting::get('contact_email_info', 'info@affilstack.com'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Contact page image')
                    ->description('Shown beside your contact details on the public Contact page. Leave blank to use a branded placeholder instead.')
                    ->components([
                        WebpFileUpload::make('contact_image')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('contact')
                            ->imageEditor()
                            ->helperText('A photo of your team, office, or founder works well here — landscape orientation, at least 800×600.'),
                    ]),

                Section::make('Contact emails')
                    ->description('The support email (used to route every Contact Us submission and shown as your main support address) is set in Brand Settings > Identity. These two are shown alongside it as extra front-door addresses.')
                    ->columns(2)
                    ->components([
                        TextInput::make('contact_email_hello')
                            ->label('Hello email')
                            ->email()
                            ->helperText('General inquiries.'),
                        TextInput::make('contact_email_info')
                            ->label('Info email')
                            ->email()
                            ->helperText('Sales / partnership inquiries.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('contact_image_path', $data['contact_image'] ?? null);
        SiteSetting::set('contact_email_hello', $data['contact_email_hello'] ?? null);
        SiteSetting::set('contact_email_info', $data['contact_email_info'] ?? null);

        Notification::make()->title('Contact page settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
