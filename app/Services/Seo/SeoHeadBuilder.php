<?php

namespace App\Services\Seo;

use App\Models\PageSeo;
use App\Models\Plan;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Support\Seo\PublicPages;
use App\Support\Seo\RenderedPageSnapshot;
use App\Support\Seo\SeoImage;
use App\Support\Seo\SeoInput;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves everything the public layout's <head> prints for one page —
 * title, description, canonical, robots, keywords, Open Graph, X card and
 * the JSON-LD graph — from, in priority order:
 *
 *  1. the page's own admin SEO (a PageSeo row for route-based pages, the
 *     SitePage row for content pages),
 *  2. what the view itself declared (@section('title' | 'meta_description'
 *     | 'og_image' | 'robots')), which is the built-in default copy,
 *  3. the sitewide defaults in SEO > SEO settings.
 *
 * Page bodies that already print their own JSON-LD (the homepage's
 * SoftwareApplication, About's AboutPage, Help/Affiliate FAQPage, Learning
 * Centre CollectionPage) are detected so the same type is never declared
 * twice on one page.
 */
class SeoHeadBuilder
{
    public const DEFAULT_TITLE_TEMPLATE = '%page% · %site%';

    public function __construct(protected OrganizationSchema $organization) {}

    /**
     * @param  array{route?: ?string, page?: mixed, title?: ?string, description?: ?string, og_image?: ?string, robots?: ?string, body?: string}  $context
     * @return array<string, mixed>
     */
    public function build(array $context): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $routeName = $context['route'] ?? null;
        $sitePage = ($context['page'] ?? null) instanceof SitePage ? $context['page'] : null;
        $pageSeo = PageSeo::forRoute($routeName);

        // About is a route page (its body lives in Site > About Page) whose
        // legacy SitePage row may still carry SEO — the PageSeo row wins and
        // the view's sections already fall back to that SitePage.
        $isContentPage = $sitePage !== null && $pageSeo === null;
        $input = $pageSeo?->seoInput() ?? $sitePage?->seoInput() ?? new SeoInput;

        $pageName = $isContentPage
            ? $sitePage->title
            : ($routeName && PublicPages::has($routeName) ? PublicPages::label($routeName) : $siteName);

        $title = $this->resolveTitle($input, $isContentPage ? $sitePage : null, $pageName, $siteName, $context['title'] ?? null);
        $description = $input->metaDescription
            ?: trim((string) ($context['description'] ?? ''))
            ?: (string) SiteSetting::get('seo_meta_description', '');
        $canonical = $input->canonicalUrl ?: url()->current();
        $robots = $input->robotsDirective() ?? (trim((string) ($context['robots'] ?? '')) ?: null);

        $ogImage = $this->image($input->ogImagePath)
            ?? $this->urlImage($context['og_image'] ?? null)
            ?? $this->image(SiteSetting::get('seo_og_image_path'));
        $twitterImage = $this->image($input->twitterImagePath) ?? $ogImage;

        $ogTitle = $input->ogTitle ?: $title;
        $ogDescription = $input->ogDescription ?: $description;
        $ogType = $isContentPage ? 'article' : 'website';

        $keywords = $input->allKeywords();

        if ($routeName !== null && PublicPages::has($routeName)) {
            RenderedPageSnapshot::remember(
                $routeName,
                trim((string) ($context['title'] ?? '')) ?: $siteName,
                trim((string) ($context['description'] ?? '')) ?: (string) SiteSetting::get('seo_meta_description', ''),
                (string) ($context['body'] ?? ''),
            );
        }

        $graph = [$this->organization->organizationNode(), $this->organization->websiteNode()];
        $pageNode = $this->pageNode(
            $input->schemaType ?: ($routeName && PublicPages::has($routeName) ? PublicPages::defaultSchemaType($routeName) : 'WebPage'),
            $canonical,
            $title,
            $description,
            $keywords,
            $ogImage['url'] ?? null,
            $isContentPage ? $sitePage->updated_at?->toIso8601String() : null,
            (string) ($context['body'] ?? ''),
        );

        if ($pageNode !== null) {
            $graph[] = $pageNode;
        }

        return [
            'site_name' => $siteName,
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'keywords' => $keywords,
            'tags' => $input->tags,
            'og' => [
                'type' => $ogType,
                'title' => $ogTitle,
                'description' => $ogDescription,
                'image' => $ogImage,
                'modified_time' => $isContentPage ? $sitePage->updated_at?->toIso8601String() : null,
            ],
            'twitter' => [
                'card' => $twitterImage ? 'summary_large_image' : 'summary',
                'title' => $input->twitterTitle ?: $ogTitle,
                'description' => $input->twitterDescription ?: $ogDescription,
                'image' => $twitterImage,
                'site' => $this->twitterHandle(),
            ],
            'facebook_app_id' => SiteSetting::get('seo_facebook_app_id') ?: null,
            'json_ld' => ['@context' => 'https://schema.org', '@graph' => $graph],
            'custom_json_ld' => $this->customSchema($input->customSchema),
        ];
    }

    /**
     * Fills %page% and %site% in a title or template.
     */
    public function renderTitle(string $template, string $pageName, string $siteName): string
    {
        return trim(str_replace(['%page%', '%title%', '%site%', '%sitename%'], [$pageName, $pageName, $siteName, $siteName], $template));
    }

    public function titleTemplate(): string
    {
        return (string) (SiteSetting::get('seo_title_template') ?: self::DEFAULT_TITLE_TEMPLATE);
    }

    protected function resolveTitle(SeoInput $input, ?SitePage $contentPage, string $pageName, string $siteName, ?string $viewTitle): string
    {
        if ($input->seoTitle) {
            return $this->renderTitle($input->seoTitle, $pageName, $siteName);
        }

        if ($contentPage !== null) {
            return $this->renderTitle($this->titleTemplate(), $contentPage->title, $siteName);
        }

        return trim((string) $viewTitle) ?: $siteName;
    }

    /**
     * @return array{url: string, width: ?int, height: ?int, type: ?string}|null
     */
    protected function image(mixed $path): ?array
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $size = SeoImage::dimensions($path);

        return [
            'url' => (string) SeoImage::url($path),
            'width' => $size['width'] ?? null,
            'height' => $size['height'] ?? null,
            'type' => SeoImage::mimeType($path),
        ];
    }

    /**
     * A view's @section('og_image') is already a full URL to a public-disk
     * file — map it back to its path so the original (non-WebP) file and
     * its size can be used.
     *
     * @return array{url: string, width: ?int, height: ?int, type: ?string}|null
     */
    protected function urlImage(mixed $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $prefix = rtrim(Storage::disk('public')->url(''), '/').'/';

        if (str_starts_with($url, $prefix)) {
            return $this->image(substr($url, strlen($prefix)));
        }

        return ['url' => $url, 'width' => null, 'height' => null, 'type' => null];
    }

    protected function twitterHandle(): ?string
    {
        $handle = trim((string) SiteSetting::get('seo_twitter_handle', ''));

        if ($handle === '') {
            return null;
        }

        $handle = preg_replace('#^https?://(www\.)?(twitter|x)\.com/#i', '', $handle);

        return '@'.ltrim((string) $handle, '@/');
    }

    /**
     * Re-encodes an admin's custom JSON-LD so it can never break out of its
     * script tag, whatever it contains. Invalid JSON (only possible if it
     * bypassed form validation) is dropped rather than printed.
     */
    protected function customSchema(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) : null;
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<string, mixed>|null
     */
    protected function pageNode(string $type, string $url, string $title, string $description, array $keywords, ?string $image, ?string $modified, string $body): ?array
    {
        if ($type === 'none' || ! array_key_exists($type, PublicPages::SCHEMA_TYPES)) {
            return null;
        }

        $declared = fn (string $schemaType) => (bool) preg_match('/"@type"\s*:\s*"'.preg_quote($schemaType, '/').'"/', $body);

        // An FAQPage needs real questions — only pages that render them
        // (and already print their own FAQPage) can be one.
        if ($type === 'FAQPage') {
            if ($declared('FAQPage')) {
                return null;
            }

            $type = 'WebPage';
        }

        if ($declared($type)) {
            return null;
        }

        $organizationRef = ['@id' => $this->organization->organizationId()];

        if (in_array($type, ['SoftwareApplication', 'Product'], true)) {
            return $this->productNode($type, $url, $description, $image, $organizationRef);
        }

        return array_filter([
            '@type' => $type,
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $title,
            'description' => $description ?: null,
            'inLanguage' => 'en',
            'isPartOf' => ['@id' => $this->organization->websiteId()],
            'publisher' => $organizationRef,
            'about' => $type === 'AboutPage' ? $organizationRef : null,
            'keywords' => $keywords ? implode(', ', $keywords) : null,
            'primaryImageOfPage' => $image ? ['@type' => 'ImageObject', 'url' => $image] : null,
            'dateModified' => $modified,
        ], fn ($value) => $value !== null);
    }

    /**
     * SoftwareApplication / Product with one Offer per active public plan —
     * real prices from the Plans table, never invented.
     *
     * @param  array<string, string>  $organizationRef
     * @return array<string, mixed>
     */
    protected function productNode(string $type, string $url, string $description, ?string $image, array $organizationRef): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');

        $offers = Plan::activePublicList()
            ->filter(fn ($plan) => $plan->price_monthly_cents > 0)
            ->map(fn ($plan) => [
                '@type' => 'Offer',
                'name' => $plan->name.' (monthly)',
                'price' => number_format($plan->price_monthly_cents / 100, 2, '.', ''),
                'priceCurrency' => strtoupper((string) ($plan->currency ?: 'USD')),
                'url' => route('registration.pricing'),
                'availability' => 'https://schema.org/InStock',
            ])
            ->values()
            ->all();

        $node = [
            '@type' => $type,
            '@id' => $url.'#'.strtolower($type),
            'name' => $siteName,
            'description' => $description ?: null,
            'url' => $url,
            'image' => $image,
        ];

        if ($type === 'SoftwareApplication') {
            $node += [
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'publisher' => $organizationRef,
            ];
        } else {
            $node['brand'] = $organizationRef;
        }

        $node['offers'] = $offers ?: null;

        return array_filter($node, fn ($value) => $value !== null);
    }
}
