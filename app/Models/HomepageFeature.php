<?php

namespace App\Models;

use App\Support\SafeHtml;
use App\Support\YouTubeUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A feature card on the public homepage. Admin-managed (Filament, "Content"
 * group) so the page can showcase every module without a code deploy.
 */
#[Fillable(['title', 'description', 'icon', 'media_type', 'image_path', 'default_image', 'youtube_url', 'link_url', 'sort_order', 'is_active'])]
class HomepageFeature extends Model
{
    /**
     * @var Collection<int, self>|null
     */
    protected static ?Collection $previewOverride = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * What the homepage's feature grid should actually query — normally
     * just the real active features (marketing/home.blade.php calls this
     * instead of querying directly), but during a Tony (the Creative
     * Agent) preview this returns the request-scoped override list
     * instead. See withPreviewFeature() / CreativeTaskPreviewController.
     *
     * @return Collection<int, self>
     */
    public static function previewAwareActiveList(): Collection
    {
        // Audit item #7 (caching/performance) — the homepage is the
        // highest-traffic page on the site and this query ran on every
        // single load; cached indefinitely, busted from booted() below.
        // Never cached during a Tony preview — that branch always returns
        // the request-scoped override untouched.
        //
        // Caches plain attribute arrays, never the Eloquent models — see
        // Plan::activePublicList() for why: a persistent cache store can
        // hand back an unusable __PHP_Incomplete_Class for a serialized
        // Model on the next request, where raw arrays always round-trip
        // safely.
        if (static::$previewOverride !== null) {
            return static::$previewOverride;
        }

        $rows = Cache::rememberForever('homepage_features:active_list', function () {
            return static::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (self $feature) => $feature->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('homepage_features:active_list'));
        static::deleted(fn () => Cache::forget('homepage_features:active_list'));
    }

    /**
     * Renders $callback with the real active feature list plus (or
     * replacing, if $replaceId matches an existing one) $draftFeature —
     * never persists anything, and always clears the override afterward
     * even if rendering throws.
     */
    public static function withPreviewFeature(self $draftFeature, ?int $replaceId, callable $callback): mixed
    {
        static::$previewOverride = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->reject(fn (self $feature) => $replaceId && $feature->id === $replaceId)
            ->push($draftFeature)
            ->sortBy('sort_order')
            ->values();

        try {
            return $callback();
        } finally {
            static::$previewOverride = null;
        }
    }

    public function youtubeEmbedUrl(): ?string
    {
        return static::youtubeEmbedUrlFrom($this->youtube_url);
    }

    public function youtubeThumbnailUrl(): ?string
    {
        return static::youtubeThumbnailUrlFrom($this->youtube_url);
    }

    /**
     * The video ID for any YouTube URL shape, or null — see YouTubeUrl,
     * the one place that understands YouTube's URL formats (also used by
     * the homepage hero and "See it in action" video, which store their
     * own URLs as SiteSettings rather than rows here).
     */
    public static function youtubeIdFrom(?string $url): ?string
    {
        return YouTubeUrl::idFrom($url);
    }

    /**
     * A ready-to-embed (youtube-nocookie) URL, or null if the value isn't
     * recognizable — callers should fall back to a plain link in that case
     * rather than embedding.
     */
    public static function youtubeEmbedUrlFrom(?string $url): ?string
    {
        return YouTubeUrl::embedUrl($url);
    }

    /**
     * YouTube's own thumbnail CDN — used to render a lightweight
     * click-to-play facade instead of loading every embedded video's
     * iframe up front (keeps the homepage fast on first load).
     */
    public static function youtubeThumbnailUrlFrom(?string $url): ?string
    {
        return YouTubeUrl::thumbnailUrl($url);
    }

    /**
     * The card's picture: an uploaded image, else its shipped screenshot.
     */
    public function imageUrl(): ?string
    {
        return FeaturePage::imageUrl($this->media_type === 'image' ? $this->image_path : null, $this->default_image);
    }

    /**
     * "Learn more" target — a site path or http(s) link only.
     */
    public function safeLinkUrl(): ?string
    {
        $url = trim((string) $this->link_url);

        return $url !== '' && SafeHtml::isSafeUrl($url) ? $url : null;
    }
}
