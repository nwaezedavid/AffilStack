<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\ConnectionsHealth;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Support\Docs\IntegrationGuideRegistry;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The "setup checklist" hub: every third-party integration AffilStack
 * connects to, as a card in launch order (foundations → sign-in & analytics
 * → customer features → growth), each with its live status, cost, setup
 * time and links to its step-by-step guide (IntegrationGuide) and to the
 * admin page where its keys go. Metadata lives in config/integration_guides.php.
 */
class IntegrationGuides extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Integration Guides';

    protected static ?string $title = 'Integration Guides';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.documentation.integration-guides';

    /**
     * Keeps this sidebar item highlighted while reading any single guide.
     *
     * @return array<int, string>
     */
    public static function getNavigationItemActiveRoutePattern(): array
    {
        return [static::getRouteName(), IntegrationGuide::getRouteName()];
    }

    public function registry(): IntegrationGuideRegistry
    {
        return app(IntegrationGuideRegistry::class);
    }

    /**
     * @return array<string, array{title: string, description: string, guides: array<int, array<string, mixed>>}>
     */
    public function phases(): array
    {
        $registry = $this->registry();

        return collect($registry->phases())
            ->map(function (array $phase) use ($registry): array {
                $phase['guides'] = array_map(fn (array $guide): array => array_merge($guide, [
                    'status' => $registry->status($guide['key']),
                    'url' => $registry->guideUrl($guide['key']),
                    'target' => $registry->adminTarget($guide['key']),
                ]), $phase['guides']);

                return $phase;
            })
            ->all();
    }

    /**
     * @param  array<string, array{guides: array<int, array<string, mixed>>}>  $phases
     * @return array{total: int, working: int, required_open: int, attention: int}
     */
    public function summary(array $phases): array
    {
        $guides = collect($phases)->flatMap(fn (array $phase) => $phase['guides']);

        return [
            'total' => $guides->count(),
            'working' => $guides->filter(fn (array $guide) => in_array($guide['status']['state'], ['working', 'user'], true))->count(),
            'required_open' => $guides->filter(fn (array $guide) => $guide['priority'] === 'required' && $guide['status']['state'] !== 'working')->count(),
            'attention' => $guides->filter(fn (array $guide) => $guide['status']['state'] === 'failing')->count(),
        ];
    }

    /**
     * Plain-text index of every guide for the client-side search box —
     * each guide's own rendered text, so search finds anything written
     * in a guide (e.g. "redirect_uri_mismatch", "App Password", "CASA").
     *
     * @return array<int, array{key: string, title: string, url: string, text: string}>
     */
    public function searchIndex(): array
    {
        $registry = $this->registry();

        return collect($registry->all())
            ->map(fn (array $guide, string $key): array => [
                'key' => $key,
                'title' => $guide['title'],
                'url' => $registry->guideUrl($key),
                'text' => Str::of(strip_tags(IntegrationGuide::renderBody($key)))->squish()->limit(12000)->toString().' '.$guide['enables'],
            ])
            ->values()
            ->all();
    }

    public function connectionsHealthUrl(): string
    {
        return ConnectionsHealth::getUrl();
    }
}
