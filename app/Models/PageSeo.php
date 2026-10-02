<?php

namespace App\Models;

use App\Support\Seo\PublicPages;
use App\Support\Seo\SeoInput;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-controlled SEO for one route-based public page (home, pricing,
 * help, learn, contact, about, affiliate landing) — the pages that are a
 * Blade view rather than a SitePage row. Keyed by route name; every field
 * is optional and falls back to the view's own built-in title/description
 * and the sitewide defaults in SEO Settings. See SeoHeadBuilder.
 */
#[Fillable([
    'route_name', 'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'tags', 'canonical_url',
    'robots_index', 'robots_follow', 'og_title', 'og_description', 'og_image_path', 'twitter_title',
    'twitter_description', 'twitter_image_path', 'schema_type', 'custom_schema',
])]
class PageSeo extends Model
{
    protected $table = 'page_seo';

    protected function casts(): array
    {
        return [
            'secondary_keywords' => 'array',
            'tags' => 'array',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    /**
     * Cached per route like SitePage::published() — a plain attribute array
     * (never the serialised model) so any cache store round-trips it safely.
     * A missing row is not cached; it costs one indexed lookup.
     */
    public static function forRoute(?string $routeName): ?self
    {
        if ($routeName === null || ! PublicPages::has($routeName)) {
            return null;
        }

        $row = Cache::get("page_seo:{$routeName}");

        if ($row === null) {
            $row = static::where('route_name', $routeName)->first()?->getAttributes();

            if ($row !== null) {
                Cache::forever("page_seo:{$routeName}", $row);
            }
        }

        return $row ? (new static)->newFromBuilder($row) : null;
    }

    /**
     * Makes sure every page in the registry has a row, so the admin list
     * always shows the full set of public pages.
     */
    public static function syncRegistry(): void
    {
        $existing = static::pluck('route_name')->all();

        foreach (array_keys(PublicPages::all()) as $routeName) {
            if (! in_array($routeName, $existing, true)) {
                static::create(['route_name' => $routeName, 'schema_type' => PublicPages::defaultSchemaType($routeName)]);
            }
        }
    }

    public function label(): string
    {
        return PublicPages::label($this->route_name);
    }

    public function url(): ?string
    {
        return PublicPages::url($this->route_name);
    }

    public function seoInput(): SeoInput
    {
        return SeoInput::fromArray([
            ...$this->only([
                'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'tags', 'canonical_url',
                'og_title', 'og_description', 'og_image_path', 'twitter_title', 'twitter_description',
                'twitter_image_path', 'schema_type', 'custom_schema',
            ]),
            'index' => $this->robots_index ?? true,
            'follow' => $this->robots_follow ?? true,
        ]);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $page) => Cache::forget("page_seo:{$page->route_name}"));
        static::deleted(fn (self $page) => Cache::forget("page_seo:{$page->route_name}"));
    }
}
