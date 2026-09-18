<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ScopedToDepartment;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * "If for any reason in the future I decided to discontinue the in-house
 * affiliate program and switch to external network like Partnerstack, I
 * should be able to toggle the feature off from my dashboard and remove it
 * from the menu." One switch, off by nothing else:
 *
 * - Hides the "Affiliate Program" link from the public site menu
 *   (layouts/marketing.blade.php).
 * - Redirects the public landing page (/affiliate or the configured
 *   subdomain) back to the homepage instead of showing a stale
 *   application form — see AffiliateController::show().
 * - Rejects a submission even if someone posts directly to the apply
 *   endpoint — see AffiliateApplicationService::submit().
 * - Drops the landing page from the sitemap — see SeoController::sitemap().
 *
 * Deliberately does NOT touch anything else: every paying customer is
 * still automatically an affiliate (Dashboard > Referrals), and an
 * already-approved affiliate-only account keeps full access to their own
 * Partner Portal (link, stats, payout requests) — turning this off only
 * stops RECRUITING new affiliates through the public program, it never
 * takes away commission someone already earned.
 */
class AffiliateProgramSettings extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Affiliate Program';

    protected static ?string $title = 'Affiliate Program Settings';

    protected string $view = 'filament.pages.affiliate-program-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'affiliate_program_enabled' => SiteSetting::flag('affiliate_program_enabled'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Public recruiting')
                    ->description('Controls the "Affiliate Program" link in the site menu and the public application page — nothing else.')
                    ->components([
                        Toggle::make('affiliate_program_enabled')
                            ->label('Accept new affiliate applications')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->helperText(
                                'Turn this off if you\'re discontinuing the in-house program — for example, switching to '.
                                'an external network like PartnerStack instead. It immediately removes the "Affiliate '.
                                'Program" link from the site menu, takes the public application page and sitemap entry '.
                                'down, and stops any new submission — even a direct POST to the apply endpoint. Every '.
                                'paying customer keeps their automatic referral link (Dashboard > Referrals) either way, '.
                                'and any affiliate you already approved keeps full access to their Partner Portal and '.
                                'can still request payouts for commission they already earned.'
                            ),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::setFlag('affiliate_program_enabled', (bool) ($data['affiliate_program_enabled'] ?? false));

        Notification::make()->title('Affiliate program settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save changes')->submit('save'),
        ];
    }
}
