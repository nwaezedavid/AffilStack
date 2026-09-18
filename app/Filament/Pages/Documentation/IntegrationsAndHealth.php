<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\ConnectionsHealth;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Filament\Pages\FundingHealth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class IntegrationsAndHealth extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    protected static ?string $navigationLabel = 'Integrations & Health';

    protected static ?string $title = 'Integrations & Health';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.documentation.section-page';

    public function headerIcon(): Heroicon
    {
        return Heroicon::OutlinedBoltSlash;
    }

    public function intro(): string
    {
        return 'Every external service this panel connects to, and the one page that tells you at a glance '.
            'whether all of them are actually working.';
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedHeart,
                'title' => 'Connections Health (AI-assisted verification)',
                'url' => ConnectionsHealth::getUrl(),
                'body' => [
                    'One page that checks every external integration this panel configures — payment gateways, '.
                        'PartnerStack, Google/LinkedIn/TikTok/Instagram, the Brain agent\'s Anthropic key and Meta '.
                        'MCP connection, and HeyGen — and reports one of four states per item: not configured, '.
                        'configured but never verified, verified and working, or verified and failing.',
                    '"Run all live checks" actually calls each service\'s API with the stored credentials — the '.
                        'same verification each settings page already runs when you save it, just gathered in one '.
                        'place. A few integrations (LinkedIn, TikTok, Instagram) use an OAuth consent flow that '.
                        'can\'t be pinged with a stored key alone — those are marked "Configured" with a note on '.
                        'exactly what to click-test on the real frontend instead.',
                    'Every item has an "Explain with AI" button that turns its current status into a '.
                        'plain-language explanation of what\'s wrong and how to fix it, for whoever is on call and '.
                        'didn\'t set the integration up originally.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedBanknotes,
                'title' => 'Funding Watch (Vault, the funding-monitor agent)',
                'url' => FundingHealth::getUrl(),
                'body' => [
                    'Vault watches every account that needs to stay funded so a user\'s action never fails '.
                        'mid-flight — HeyGen\'s video-render credits and the payout wallet balance are checked '.
                        'live against a threshold you set here; Anthropic API billing and Meta ad spend instead '.
                        'get an admin-set reminder cadence (in days) that Vault nags you about until you click '.
                        '"I topped up — reset reminder".',
                    'Every check runs daily on its own (see System > Scheduled Task Runs for the run history). A '.
                        'low balance or overdue reminder notifies every full admin exactly once per crossing — it '.
                        'won\'t repeat the same alert every day while nothing has changed.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedCog6Tooth,
                'title' => 'Other integrations',
                'url' => null,
                'body' => [
                    'Google Login, LinkedIn Connect, TikTok Publishing, Instagram Publishing, Brain Settings (the '.
                        'AI agent\'s Anthropic key and Meta MCP connection), and UGC Video Settings (HeyGen) each '.
                        'have their own settings page under System/AI Agents in the navigation. Every one of them '.
                        'shows up on Connections Health above — that\'s the fastest way to confirm all of them are '.
                        'actually working rather than opening each page individually.',
                ],
            ],
        ];
    }
}
