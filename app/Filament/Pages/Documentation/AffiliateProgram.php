<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\AffiliateProgramSettings;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Filament\Pages\PartnerStackSettings;
use App\Filament\Resources\AffiliateApplications\AffiliateApplicationResource;
use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AffiliateProgram extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Affiliate Program';

    protected static ?string $title = 'Affiliate Program';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.documentation.section-page';

    public function headerIcon(): Heroicon
    {
        return Heroicon::OutlinedUserGroup;
    }

    public function intro(): string
    {
        return 'How the in-house affiliate program works, how to turn it off if you ever switch to an external '.
            'network, and how affiliates actually get paid.';
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedUserPlus,
                'title' => 'How affiliates work',
                'url' => AffiliateApplicationResource::getUrl(),
                'body' => [
                    'Every paying customer is already an affiliate automatically the moment they log in (Dashboard '.
                        '> Referrals) — this application flow is only for someone who wants to become an affiliate '.
                        'WITHOUT ever becoming a customer. They apply from the public affiliate landing page '.
                        '(served at the AFFILIATE_SUBDOMAIN environment variable\'s subdomain when set, e.g. '.
                        'affiliate.affilstack.com — otherwise a plain /affiliate page on the main domain), and every '.
                        'application shows up here as "pending" until you Approve or Reject it.',
                    'Approving an application creates their account and emails them a one-time link to set their '.
                        'own password — they land straight in a distinctly-branded "Partner Portal" version of the '.
                        'dashboard (their sidebar and banner make clear this account earns commission and has no '.
                        'platform access) showing their tracking link, click/commission stats, and payout request '.
                        'form. This kind of account can only ever reach that page, their own profile, and logout — '.
                        'never any paid feature, since they were never a customer.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedMegaphone,
                'title' => 'Turning the public program on or off',
                'url' => AffiliateProgramSettings::getUrl(),
                'body' => [
                    'If you ever discontinue in-house recruiting — for example, switching entirely to an external '.
                        'network like PartnerStack below — turn off "Accept new affiliate applications" here. It '.
                        'immediately removes the "Affiliate Program" link from the site menu, redirects the public '.
                        'application page back to the homepage, drops it from the sitemap, and rejects any new '.
                        'submission even if someone posts directly to the apply endpoint.',
                    'Nothing else changes: every paying customer keeps their automatic referral link, and anyone '.
                        'you already approved keeps full access to their Partner Portal and can still request a '.
                        'payout for commission they already earned. This switch only stops recruiting new '.
                        'affiliates through the public program — it\'s reversible any time, and no data is deleted.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedPuzzlePiece,
                'title' => 'PartnerStack (affiliate tracking)',
                'url' => PartnerStackSettings::getUrl(),
                'body' => [
                    'Connects AffilStack\'s own referral program to PartnerStack for cross-platform affiliate '.
                        'tracking. Enter the API key/public key pair from your PartnerStack dashboard here, then '.
                        'use Connections Health (Integrations & Health) to verify the key was accepted.',
                    'This is separate from the in-app Referral Payouts/Payout Wallet system below — PartnerStack '.
                        'is an external tracking integration, while Referral Payouts and the Payout Wallet are how '.
                        'commissions actually get calculated and paid out from inside AffilStack itself. If you '.
                        'move to PartnerStack as your primary program, you\'d turn the toggle above off and rely on '.
                        'PartnerStack for new recruiting instead — the two can also run side by side.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedWallet,
                'title' => 'Payout wallet (affiliate payouts)',
                'url' => WalletTransactionResource::getUrl(),
                'body' => [
                    'A running ledger you fund with real money you\'ve already moved into your own Flutterwave '.
                        'balance or PayPal account — "Add funds" here records that top-up; it never moves money by '.
                        'itself. The balance shown is always the sum of every ledger row for that currency, so it '.
                        'can never drift out of sync with what actually happened.',
                    'From Referral Payouts, a "Disburse from wallet" button appears next to any requested payout '.
                        'this wallet can pay automatically. Every disbursement still requires an admin to click the '.
                        'button — nothing here ever moves real money on a schedule or without a human deciding to '.
                        'do it right then.',
                ],
            ],
        ];
    }
}
