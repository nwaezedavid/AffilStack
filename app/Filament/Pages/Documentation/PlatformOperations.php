<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Filament\Pages\SeoSettings;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PlatformOperations extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Platform Operations';

    protected static ?string $title = 'Platform Operations';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.documentation.section-page';

    public function headerIcon(): Heroicon
    {
        return Heroicon::OutlinedServerStack;
    }

    public function intro(): string
    {
        return 'How the site stays fast, and how it stays visible to search engines.';
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedBoltSlash,
                'title' => 'Caching & performance',
                'url' => null,
                'body' => [
                    'Public marketing content (the pricing page, homepage feature cards, FAQ, and static pages '.
                        'like About/Terms/Privacy/Refund Policy) is cached indefinitely and refreshed automatically '.
                        'the instant you save a change in Filament — there is no "clear cache" step to remember '.
                        'after editing Plans, Site Pages, FAQ, Homepage Features, Brand Logos, Testimonials, or '.
                        'Tutorials.',
                    'Those same pages also tell browsers (and any CDN in front of the site) they can be reused for '.
                        'a few minutes, so a repeat visit doesn\'t always round-trip through the server — this '.
                        'backs off automatically on any response carrying a flash message, so an error or success '.
                        'banner is never accidentally shown to the wrong visitor. If a page ever looks stale right '.
                        'after an edit, it\'s almost always this few-minutes browser cache (a hard refresh fixes '.
                        'it) rather than the server, which invalidates the moment you hit Save.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedMagnifyingGlassCircle,
                'title' => 'Search engine visibility',
                'url' => SeoSettings::getUrl(),
                'body' => [
                    'robots.txt is free-text editable here; the sitemap at /sitemap.xml is generated automatically '.
                        'from the app\'s own public routes (home, pricing, the affiliate landing page when it\'s '.
                        'turned on, the Learning Centre, About, Help, and your blog subdomain if you\'ve set one) '.
                        'rather than needing to be maintained by hand.',
                    'The homepage\'s star-rating rich-result markup is always computed from real, currently-'.
                        'published testimonial ratings, and only appears once the visible Testimonials section '.
                        'itself does — it can never claim a rating that isn\'t backed by content an actual visitor '.
                        'can see on the page.',
                ],
            ],
        ];
    }
}
