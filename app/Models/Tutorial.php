<?php

namespace App\Models;

use Database\Factories\TutorialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One video tutorial on the public /learn "Learning Centre" page —
 * "create a tutorial topic/title, and then add the YouTube video link to
 * be embedded in the frontend for the public." Admin-managed (Filament:
 * Content > Tutorials).
 */
#[Fillable(['title', 'slug', 'description', 'youtube_url', 'category', 'sort_order', 'is_published'])]
class Tutorial extends Model
{
    /** @use HasFactory<TutorialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tutorial) {
            if (! $tutorial->slug) {
                $tutorial->slug = static::uniqueSlugFor($tutorial->title);
            }
        });

        static::saved(fn () => Cache::forget('tutorials:published_grouped'));
        static::deleted(fn () => Cache::forget('tutorials:published_grouped'));
    }

    protected static function uniqueSlugFor(string $title): string
    {
        $base = Str::slug($title) ?: 'tutorial';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Grouped-by-category, cached indefinitely and busted on save/delete —
     * same pattern as FaqItem::publishedGrouped(). Tutorials with no
     * category land under a single null-keyed group, rendered as
     * "General" by the view.
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, self>>
     */
    public static function publishedGrouped(): Collection
    {
        $rows = Cache::rememberForever('tutorials:published_grouped', function () {
            return static::where('is_published', true)->orderBy('sort_order')->get()
                ->map(fn (self $tutorial) => $tutorial->getAttributes())->all();
        });

        return static::hydrate($rows)->groupBy('category');
    }

    public function youtubeEmbedUrl(): ?string
    {
        return HomepageFeature::youtubeEmbedUrlFrom($this->youtube_url);
    }

    public function youtubeThumbnailUrl(): ?string
    {
        return HomepageFeature::youtubeThumbnailUrlFrom($this->youtube_url);
    }
}
