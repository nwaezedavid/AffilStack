<?php

namespace App\Models;

use App\Support\Seo\SeoInput;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A long-form static page (About, Terms, Privacy Policy, etc.) whose title
 * and body the admin can edit from Filament without a code deploy. Looked
 * up by slug — see PageController.
 */
#[Fillable([
    'slug', 'title', 'seo_title', 'meta_description', 'og_image_path', 'focus_keyword', 'content', 'is_published', 'no_index',
    'secondary_keywords', 'tags', 'canonical_url', 'no_follow', 'og_title', 'og_description', 'twitter_title',
    'twitter_description', 'twitter_image_path', 'schema_type', 'custom_schema',
])]
class SitePage extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'no_index' => 'boolean',
            'no_follow' => 'boolean',
            'secondary_keywords' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * The page's SEO fields in the shape SeoHeadBuilder and SeoScore share
     * with PageSeo — see SeoInput.
     */
    public function seoInput(): SeoInput
    {
        return SeoInput::fromArray([
            ...$this->only([
                'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'tags', 'canonical_url',
                'og_title', 'og_description', 'og_image_path', 'twitter_title', 'twitter_description',
                'twitter_image_path', 'schema_type', 'custom_schema',
            ]),
            'index' => ! $this->no_index,
            'follow' => ! $this->no_follow,
        ]);
    }

    /**
     * The <title>/OG title to actually render — falls back to the on-page
     * H1 (`title`) when an admin hasn't written a separate SEO title.
     */
    public function displayTitle(): string
    {
        return $this->seo_title ?: $this->title;
    }

    /**
     * Audit item #7 (caching/performance) — every visit to /about, /terms,
     * /refund-policy, etc. looked this row up fresh; these pages change
     * only from a Filament edit, so this caches per-slug indefinitely and
     * is busted from booted() below whenever that row (or its old slug, if
     * renamed) is saved or deleted.
     *
     * Caches a plain attribute array (or null), never the Eloquent model
     * itself — see Plan::activePublicList() for why: a persistent cache
     * store can hand back an unusable __PHP_Incomplete_Class for a
     * serialized Model on the next request, where a raw array always
     * round-trips safely.
     */
    public static function published(string $slug): ?self
    {
        // Hits only — a cached miss is never served back anyway and would
        // leave a permanent cache row per slug a bot tries.
        $row = Cache::get("site_page:{$slug}");

        if ($row === null) {
            $row = static::where('slug', $slug)->where('is_published', true)->first()?->getAttributes();

            if ($row !== null) {
                Cache::forever("site_page:{$slug}", $row);
            }
        }

        return $row ? (new static)->newFromBuilder($row) : null;
    }

    protected static function booted(): void
    {
        static::saved(function (self $page): void {
            Cache::forget("site_page:{$page->slug}");

            $originalSlug = $page->getOriginal('slug');

            if ($originalSlug && $originalSlug !== $page->slug) {
                Cache::forget("site_page:{$originalSlug}");
            }
        });

        static::deleted(fn (self $page) => Cache::forget("site_page:{$page->slug}"));
    }
}
