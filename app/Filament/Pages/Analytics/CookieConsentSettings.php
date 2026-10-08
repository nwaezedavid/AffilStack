<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Clusters\Analytics;
use App\Filament\Concerns\ScopedToDepartment;
use App\Models\SiteSetting;
use App\Support\Analytics\ConsentSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
 * Agent 04: the cookie banner's wording, the policy link and version, and
 * how long analytics data is kept. Consent is always required before any
 * analytics or advertising cookie or pixel — there is intentionally no
 * switch to load them without it (NDPA 2023, GDPR/ePrivacy); turning the
 * banner off only means optional tags never load at all.
 */
class CookieConsentSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $cluster = Analytics::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Cookie Consent';

    protected static ?string $title = 'Cookie Consent';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.analytics.settings-form';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'consent_banner_enabled' => ConsentSettings::bannerEnabled(),
            'consent_banner_title' => SiteSetting::get('consent_banner_title') ?: ConsentSettings::DEFAULT_TITLE,
            'consent_banner_text' => SiteSetting::get('consent_banner_text') ?: ConsentSettings::DEFAULT_TEXT,
            'consent_policy_url' => SiteSetting::get('consent_policy_url'),
            'consent_policy_version' => ConsentSettings::policyVersion(),
            'consent_count_anonymous_views' => ConsentSettings::countsAnonymousViews(),
            'consent_google_mode' => ConsentSettings::googleMode(),
            'analytics_retention_months' => ConsentSettings::retentionMonths(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Callout::make('How consent works on your site')
                    ->info()
                    ->description('Until a visitor chooses, only strictly necessary cookies are used: Google Analytics/Tag Manager, the Meta and TikTok pixels and AffilStack\'s own visitor analytics stay switched off (Google Consent Mode v2 starts with everything "denied"). "Accept all", "Reject all" and "Customise" are equally prominent, Global Privacy Control and Do Not Track are honoured automatically, and every choice is logged as proof in Consent Log. This is how Nigeria\'s NDPA 2023, the EU/UK GDPR + ePrivacy rules and US state privacy laws expect it to work, so there is no option to load trackers before consent.'),

                Section::make('Banner')
                    ->description('What visitors see the first time they arrive (and whenever the policy version changes).')
                    ->components([
                        Toggle::make('consent_banner_enabled')
                            ->label('Show the cookie banner')
                            ->helperText('Off: no banner, and no analytics or advertising tags ever load (only anonymous page counts, if allowed below).'),
                        TextInput::make('consent_banner_title')->label('Banner title')->required()->maxLength(80),
                        Textarea::make('consent_banner_text')->label('Banner text')->required()->rows(4)->maxLength(600)
                            ->helperText('Say plainly what you use cookies for. A link to the Cookie Policy is added automatically.'),
                        TextInput::make('consent_policy_url')
                            ->label('Cookie Policy link')
                            ->placeholder(route('cookie-policy'))
                            ->maxLength(255)
                            ->regex('#^(/(?![/\\\\])|https://)#i')
                            ->validationMessages(['regex' => 'Use a site path like /cookie-policy or a full https:// link.'])
                            ->helperText('Leave empty to use the built-in Cookie Policy page (Content → Site Pages → cookie-policy), which lists the cookies actually in use automatically.'),
                    ]),

                Section::make('Policy version')
                    ->description('Visitors are asked again whenever this changes. Change it when you add a new tracking tool or a new use of the data.')
                    ->components([
                        TextInput::make('consent_policy_version')
                            ->label('Current version')
                            ->required()
                            ->maxLength(20)
                            ->regex('/^[A-Za-z0-9._-]{1,20}$/')
                            ->validationMessages(['regex' => 'Letters, numbers, dots, dashes and underscores only (e.g. 2026-10).'])
                            ->helperText('Tip: use the "Ask everyone again" button at the top of this page to bump it to today\'s date.'),
                    ]),

                Section::make('Measurement & retention')
                    ->columns(2)
                    ->components([
                        Toggle::make('consent_count_anonymous_views')
                            ->label('Count anonymous page views without consent')
                            ->helperText('Keeps total page-view counts accurate. Records only the page and the type of traffic source — no cookie, no id, nothing linked to a person.')
                            ->columnSpanFull(),
                        Select::make('consent_google_mode')
                            ->label('Google tags (GA4 / Tag Manager)')
                            ->options([
                                'basic' => 'Basic Consent Mode — load only after consent (recommended)',
                                'advanced' => 'Advanced Consent Mode — load immediately, cookieless pings until consent',
                            ])
                            ->required()
                            ->helperText('Advanced lets Google model conversions from people who decline, but sends Google a cookieless ping before consent, which some EU regulators consider non-compliant.'),
                        TextInput::make('analytics_retention_months')
                            ->label('Keep analytics data for (months)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->helperText('Older visits, events, profiles and consent records are deleted every night. 13 months is the common regulator guideline.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::setFlag('consent_banner_enabled', (bool) $data['consent_banner_enabled']);
        SiteSetting::set('consent_banner_title', $data['consent_banner_title']);
        SiteSetting::set('consent_banner_text', $data['consent_banner_text']);
        SiteSetting::set('consent_policy_url', $data['consent_policy_url'] ?: null);
        SiteSetting::set('consent_policy_version', $data['consent_policy_version']);
        SiteSetting::setFlag('consent_count_anonymous_views', (bool) $data['consent_count_anonymous_views']);
        SiteSetting::set('consent_google_mode', $data['consent_google_mode'] === 'advanced' ? 'advanced' : 'basic');
        SiteSetting::set('analytics_retention_months', (string) (int) $data['analytics_retention_months']);

        Notification::make()->title('Cookie consent settings saved')->body('Changes apply to the next page anyone loads.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bumpVersion')
                ->label('Ask everyone again')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Sets a new policy version (today\'s date and time). Every visitor sees the banner again on their next page, and nothing optional loads until they choose.')
                ->action(function (): void {
                    $version = now()->format('Y-m-d.Hi');
                    SiteSetting::set('consent_policy_version', $version);
                    $this->data['consent_policy_version'] = $version;

                    Notification::make()->title('Policy version bumped to '.$version)->success()->send();
                }),
            Action::make('preview')
                ->label('View Cookie Policy')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => ConsentSettings::policyUrl())
                ->openUrlInNewTab(),
        ];
    }
}
