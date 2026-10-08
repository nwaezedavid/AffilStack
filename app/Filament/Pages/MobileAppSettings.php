<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Filament\Support\WebpFileUpload;
use App\Models\SiteSetting;
use App\Support\MobileApps;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * "As soon as I launch the mobile apps on Google Play store and Apple
 * store, I'll update the links in the admin dashboard area and it will
 * become available and visible on the frontend." Paste a store link here
 * and the official-style download badges appear on the homepage apps band,
 * in the site footer and in every customer's dashboard sidebar, the /app
 * smart link starts routing phones to the right store, and iPhones get
 * Safari's Smart App Banner. See App\Support\MobileApps.
 */
class MobileAppSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Mobile Apps';

    protected static ?string $title = 'Mobile Apps';

    protected string $view = 'filament.pages.mobile-app-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mobile_app_play_url' => SiteSetting::get('mobile_app_play_url'),
            'mobile_app_store_url' => SiteSetting::get('mobile_app_store_url'),
            'mobile_app_coming_soon' => SiteSetting::flag('mobile_app_coming_soon', false),
            'mobile_app_name' => SiteSetting::get('mobile_app_name'),
            'mobile_app_pitch' => SiteSetting::get('mobile_app_pitch'),
            'mobile_app_screenshot' => SiteSetting::get('mobile_app_screenshot_path'),
            'mobile_app_screenshot_alt' => SiteSetting::get('mobile_app_screenshot_alt'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Callout::make('How this works')
                    ->info()
                    ->description('Nothing about the apps is shown to visitors until you paste a store link below (or switch on "Coming soon"). The moment you save a link, download badges appear on the homepage, in the site footer and in every customer\'s dashboard — and '.url('/app').' becomes a smart link that opens the right store on Android and iPhone.'),

                Section::make('Store links')
                    ->description('Copy each link from your store listing once the app is live.')
                    ->columns(2)
                    ->components([
                        TextInput::make('mobile_app_play_url')
                            ->label('Google Play link')
                            ->url()
                            ->placeholder('https://play.google.com/store/apps/details?id=com.affilstack.app')
                            ->regex('#^https://play\.google\.com/#i')
                            ->validationMessages(['regex' => 'Paste the full Google Play listing link — it starts with https://play.google.com/'])
                            ->helperText('Google Play Console → your app → "View on Google Play", then copy the address.'),
                        TextInput::make('mobile_app_store_url')
                            ->label('Apple App Store link')
                            ->url()
                            ->placeholder('https://apps.apple.com/app/affilstack/id1234567890')
                            ->regex('#^https://(apps|itunes)\.apple\.com/#i')
                            ->validationMessages(['regex' => 'Paste the full App Store link — it starts with https://apps.apple.com/'])
                            ->helperText('App Store Connect → your app → "View on App Store". The "id123…" number in it also turns on the iPhone Smart App Banner.'),
                        Toggle::make('mobile_app_coming_soon')
                            ->label('Show "Coming soon" badges before launch')
                            ->helperText('On: the apps band and footer appear now, with a "Coming soon" badge for any store that has no link yet. Off: everything stays hidden until a link is saved.')
                            ->columnSpanFull(),
                    ]),

                Section::make('How the app is presented')
                    ->columns(2)
                    ->components([
                        TextInput::make('mobile_app_name')
                            ->label('App name / band heading')
                            ->placeholder('AffilStack for iOS & Android')
                            ->maxLength(80),
                        Textarea::make('mobile_app_pitch')
                            ->label('Short pitch')
                            ->rows(2)
                            ->maxLength(220)
                            ->placeholder('Research products, follow up with buyers and check your earnings from anywhere.')
                            ->helperText('One sentence under the heading on the homepage apps band.'),
                        WebpFileUpload::make('mobile_app_screenshot')
                            ->label('App screenshot (optional)')
                            ->image()
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('mobile-app')
                            ->helperText('A portrait phone screenshot, 1080×1920 px (9:16), of the app\'s home screen — ideally the earnings or research view with realistic data. Export it WITHOUT a phone frame (the site draws one around it). PNG or WebP under 1 MB. Left empty, a designed placeholder phone is shown instead.'),
                        TextInput::make('mobile_app_screenshot_alt')
                            ->label('Screenshot description (alt text)')
                            ->placeholder('AffilStack app home screen showing this month\'s earnings')
                            ->maxLength(150)
                            ->helperText('What the screenshot shows, for screen readers and Google Images.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('mobile_app_play_url', $data['mobile_app_play_url'] ?? null);
        SiteSetting::set('mobile_app_store_url', $data['mobile_app_store_url'] ?? null);
        SiteSetting::setFlag('mobile_app_coming_soon', (bool) ($data['mobile_app_coming_soon'] ?? false));
        SiteSetting::set('mobile_app_name', $data['mobile_app_name'] ?? null);
        SiteSetting::set('mobile_app_pitch', $data['mobile_app_pitch'] ?? null);
        SiteSetting::set('mobile_app_screenshot_path', $data['mobile_app_screenshot'] ?? null);
        SiteSetting::set('mobile_app_screenshot_alt', $data['mobile_app_screenshot_alt'] ?? null);

        Notification::make()
            ->title('Mobile app settings saved')
            ->body(MobileApps::isVisible() ? 'The download badges are now live on the homepage, footer and customer dashboard.' : 'No store link yet, so the apps stay hidden from visitors.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview homepage')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => route('home').'?preview='.time().'#get-the-app')
                ->openUrlInNewTab(),
        ];
    }
}
