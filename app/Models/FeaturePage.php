<?php

namespace App\Models;

use App\Support\SafeHtml;
use App\Support\YouTubeUrl;
use Database\Factories\FeaturePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * A public feature page (/features/{slug}) and its entry in the header's
 * "Tools" mega-menu. Admin-managed under Website → Features; the default
 * copy and screenshots ship in App\Support\Features\FeatureCatalog.
 *
 * Every image has two layers: a *_default_image (a screenshot shipped in
 * public/images/features, so the page looks complete with no upload) and
 * an optional admin upload on the public disk that overrides it.
 *
 * @property array<int, array{heading?: string, body?: string, bullets?: array<int, string>|string, image_path?: ?string, default_image?: ?string, image_alt?: ?string, video_url?: ?string}>|null $sections
 * @property array<int, array{title?: string, description?: string}>|null $steps
 * @property array<int, array{question?: string, answer?: string}>|null $faqs
 */
#[Fillable([
    'slug', 'name', 'icon', 'menu_group', 'menu_description', 'hero_eyebrow', 'hero_headline', 'hero_subheadline',
    'hero_image_path', 'hero_default_image', 'hero_image_alt', 'hero_video_url', 'sections', 'steps', 'faqs',
    'cta_heading', 'cta_text', 'cta_label', 'cta_url', 'seo_title', 'meta_description', 'sort_order',
    'show_in_menu', 'is_published',
])]
class FeaturePage extends Model
{
    /** @use HasFactory<FeaturePageFactory> */
    use HasFactory;

    /**
     * The header mega-menu's columns, in order.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'research' => 'Research & prospecting',
        'outreach' => 'Outreach & CRM',
        'content' => 'Content',
        'grow' => 'Grow & track',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'steps' => 'array',
            'faqs' => 'array',
            'show_in_menu' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget('feature_pages:published');
    }

    /**
     * Every published page in menu order — cached as plain attribute arrays
     * (see Plan::activePublicList() for why never the models themselves).
     *
     * @return Collection<int, self>
     */
    public static function publishedList(): Collection
    {
        $rows = Cache::rememberForever('feature_pages:published', function () {
            return static::query()->where('is_published', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (self $page) => $page->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    /**
     * Published pages that appear in the Tools menu, grouped by column.
     *
     * @return array<string, Collection<int, self>>
     */
    public static function menuGroups(): array
    {
        $pages = static::publishedList()->filter(fn (self $page): bool => $page->show_in_menu);
        $groups = [];

        foreach (array_keys(self::GROUPS) as $key) {
            $inGroup = $pages->filter(fn (self $page): bool => $page->menu_group === $key)->values();

            if ($inGroup->isNotEmpty()) {
                $groups[$key] = $inGroup;
            }
        }

        return $groups;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function url(): string
    {
        return route('features.show', $this->slug);
    }

    /**
     * The hero screenshot: the admin's upload, else the shipped default.
     */
    public function heroImageUrl(): ?string
    {
        return static::imageUrl($this->hero_image_path, $this->hero_default_image);
    }

    /**
     * An uploaded image on the public disk wins over a shipped default in
     * public/images/features.
     */
    public static function imageUrl(?string $uploadedPath, ?string $defaultImage): ?string
    {
        if (filled($uploadedPath)) {
            return Storage::disk('public')->url($uploadedPath);
        }

        if (filled($defaultImage) && preg_match('/^[a-z0-9-]+\.(webp|png|jpg)$/', (string) $defaultImage)) {
            return asset('images/features/'.$defaultImage);
        }

        return null;
    }

    public function heroVideoEmbeddable(): bool
    {
        return YouTubeUrl::idFrom($this->hero_video_url) !== null;
    }

    /**
     * Sections ready for the view: bullets as a list, resolved image URL,
     * and a video URL only when it's a real YouTube link.
     *
     * @return array<int, array{heading: string, body: string, bullets: array<int, string>, image: ?string, image_alt: string, video_url: ?string}>
     */
    public function presentedSections(): array
    {
        return array_values(array_map(function (array $section): array {
            $bullets = $section['bullets'] ?? [];

            if (is_string($bullets)) {
                $bullets = preg_split('/\r\n|\r|\n/', $bullets) ?: [];
            }

            $bullets = array_values(array_filter(array_map(
                fn ($bullet): string => trim(is_array($bullet) ? (string) ($bullet['text'] ?? '') : (string) $bullet),
                $bullets,
            )));

            $video = trim((string) ($section['video_url'] ?? ''));

            return [
                'heading' => (string) ($section['heading'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
                'bullets' => $bullets,
                'image' => static::imageUrl($section['image_path'] ?? null, $section['default_image'] ?? null),
                'image_alt' => (string) ($section['image_alt'] ?? ($section['heading'] ?? '')),
                'video_url' => YouTubeUrl::idFrom($video) ? $video : null,
            ];
        }, array_filter($this->sections ?? [], fn ($section): bool => is_array($section) && filled($section['heading'] ?? null))));
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    public function presentedSteps(): array
    {
        return array_values(array_map(
            fn (array $step): array => ['title' => (string) $step['title'], 'description' => (string) ($step['description'] ?? '')],
            array_filter($this->steps ?? [], fn ($step): bool => is_array($step) && filled($step['title'] ?? null)),
        ));
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public function presentedFaqs(): array
    {
        return array_values(array_map(
            fn (array $faq): array => ['question' => (string) $faq['question'], 'answer' => (string) ($faq['answer'] ?? '')],
            array_filter($this->faqs ?? [], fn ($faq): bool => is_array($faq) && filled($faq['question'] ?? null) && filled($faq['answer'] ?? null)),
        ));
    }

    /**
     * The closing button's link — a site path or http(s) URL, else pricing.
     */
    public function ctaUrl(): string
    {
        $url = trim((string) $this->cta_url);

        return $url !== '' && SafeHtml::isSafeUrl($url) ? $url : route('registration.pricing');
    }
}
