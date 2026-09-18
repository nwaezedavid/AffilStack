<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The cluster's landing page (Filament\Clusters\Cluster::mount() redirects
 * the bare cluster URL here, since it's first in navigation sort order) —
 * a short welcome plus a "browse by topic" grid linking to the other five
 * pages, so a new admin sub-account isn't dropped straight into the middle
 * of one topic with no sense of what else exists.
 */
class Overview extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Admin Documentation';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.documentation.overview';

    public function intro(): string
    {
        return 'A plain-language guide to every settings area in this dashboard — what it configures, why it '.
            'exists, and how to confirm it\'s actually working on the frontend. Pick a topic below, or see '.
            'Connections Health for a live status check across every integration at once.';
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, description: string, url: string}>
     */
    public function topics(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedCreditCard,
                'title' => 'Billing & Payments',
                'description' => 'Plans, payment gateways, credit top-ups, and the automatic refund policy.',
                'url' => BillingAndPayments::getUrl(),
            ],
            [
                'icon' => Heroicon::OutlinedUserGroup,
                'title' => 'Affiliate Program',
                'description' => 'The public application flow, turning it off, PartnerStack, and the payout wallet.',
                'url' => AffiliateProgram::getUrl(),
            ],
            [
                'icon' => Heroicon::OutlinedPaintBrush,
                'title' => 'Content & Branding',
                'description' => 'Logo, colors, homepage, brand logos, testimonials, and the Learning Centre.',
                'url' => ContentAndBranding::getUrl(),
            ],
            [
                'icon' => Heroicon::OutlinedBoltSlash,
                'title' => 'Integrations & Health',
                'description' => 'Connections Health, Funding Watch, and every other external connection.',
                'url' => IntegrationsAndHealth::getUrl(),
            ],
            [
                'icon' => Heroicon::OutlinedServerStack,
                'title' => 'Platform Operations',
                'description' => 'Caching, performance, and search engine visibility.',
                'url' => PlatformOperations::getUrl(),
            ],
        ];
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedShieldCheck,
                'title' => 'Admin login & security',
                'url' => null,
                'body' => [
                    'This panel lives at a custom, hard-to-guess URL instead of the predictable "/admin" — set '.
                        'via the ADMIN_PANEL_PATH environment variable (config/admin.php). Only this admin panel\'s '.
                        'login moved; the regular customer login at /login is untouched and always stays a different '.
                        'URL from this one, on purpose — someone finding one should learn nothing about the other.',
                    'Admin sub-accounts (Filament: System) can be granted access to one or more departments '.
                        'without being a full admin — see config(\'admin.departments\') for the exact list and what '.
                        'each one covers. A full admin/super-admin always sees everything, including every page in '.
                        'this Documentation section.',
                ],
            ],
        ];
    }
}
