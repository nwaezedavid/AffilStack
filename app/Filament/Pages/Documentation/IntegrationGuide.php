<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Support\Docs\IntegrationGuideRegistry;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One step-by-step setup guide, e.g. /documentation/integration-guides/google-sign-in.
 * Not in the sidebar — reached from the Integration Guides hub. The guide's
 * prose is resources/views/filament/pages/documentation/guides/{guide}.blade.php;
 * its metadata (cost, time, admin page, status source) is config/integration_guides.php.
 */
class IntegrationGuide extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'integration-guides/{guide}';

    protected string $view = 'filament.pages.documentation.integration-guide';

    public string $guide = '';

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'integration-guide';
    }

    public function mount(string $guide): void
    {
        abort_unless(app(IntegrationGuideRegistry::class)->has($guide), 404);

        $this->guide = $guide;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->meta()['title'];
    }

    /**
     * @return array<int, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            IntegrationGuides::getUrl() => 'Integration Guides',
            $this->meta()['short'] ?? $this->meta()['title'],
        ];
    }

    public function registry(): IntegrationGuideRegistry
    {
        return app(IntegrationGuideRegistry::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->registry()->get($this->guide);
    }

    /**
     * The guide's own HTML, rendered once per request so the table of
     * contents can be read from its sections.
     */
    public static function renderBody(string $key): string
    {
        $registry = app(IntegrationGuideRegistry::class);

        return view('filament.pages.documentation.guides.'.$key, [
            'guide' => $registry->get($key),
            'urls' => $registry->urls(),
            'target' => $registry->adminTarget($key),
        ])->render();
    }

    /**
     * @return array<int, array{id: string, title: string}>
     */
    public static function tableOfContents(string $html): array
    {
        preg_match_all('/data-toc-id="([^"]+)" data-toc="([^"]+)"/', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [
            'id' => $match[1],
            'title' => html_entity_decode($match[2], ENT_QUOTES),
        ], $matches);
    }

    /**
     * @return array{previous: ?array{url: string, title: string}, next: ?array{url: string, title: string}}
     */
    public function neighbours(): array
    {
        $keys = array_keys($this->registry()->all());
        $index = array_search($this->guide, $keys, true);
        $link = fn (?string $key): ?array => $key ? ['url' => $this->registry()->guideUrl($key), 'title' => $this->registry()->get($key)['short'] ?? $key] : null;

        return [
            'previous' => $link($keys[$index - 1] ?? null),
            'next' => $link($keys[$index + 1] ?? null),
        ];
    }
}
