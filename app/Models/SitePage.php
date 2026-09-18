<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A long-form static page (About, Terms, Privacy Policy, etc.) whose title
 * and body the admin can edit from Filament without a code deploy. Looked
 * up by slug — see PageController.
 */
#[Fillable(['slug', 'title', 'seo_title', 'meta_description', 'og_image_path', 'focus_keyword', 'content', 'is_published', 'no_index'])]
class SitePage extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'no_index' => 'boolean',
        ];
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
        $row = Cache::rememberForever("site_page:{$slug}", function () use ($slug) {
            return static::where('slug', $slug)->where('is_published', true)->first()?->getAttributes();
        });

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
