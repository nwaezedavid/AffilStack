<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A RankMath-style redirect: a path that no longer resolves to a real page
 * gets sent somewhere real instead of 404ing — a URL that changed, a
 * campaign vanity link, or a fix for a 404 surfaced by NotFoundLog. Applied
 * from the fallback route (see RedirectFallbackController), which only
 * fires once every real route has already failed to match.
 */
#[Fillable(['from_path', 'to_path', 'status_code'])]
class Redirect extends Model
{
    /**
     * Every marketing-site 404 checks this — cached the same way
     * SitePage::published()/FaqItem::publishedGrouped() are, since
     * redirects change only from a rare Filament edit.
     */
    public static function forPath(string $path): ?self
    {
        $row = Cache::rememberForever(
            'redirect:'.ltrim($path, '/'),
            fn () => static::where('from_path', ltrim($path, '/'))->first()?->getAttributes()
        );

        return $row ? (new static)->newFromBuilder($row) : null;
    }

    /**
     * increment()'s $extra argument is force-filled, bypassing mass
     * assignment protection — unlike update(), which silently drops
     * last_hit_at since it (deliberately) isn't in $fillable above. A plain
     * $this->update(['last_hit_at' => now()]) here would look correct but
     * never actually persist the column.
     */
    public function recordHit(): void
    {
        $this->increment('hits_count', 1, ['last_hit_at' => now()]);
    }

    protected static function booted(): void
    {
        static::saved(function (self $redirect): void {
            Cache::forget('redirect:'.ltrim($redirect->from_path, '/'));

            $originalFromPath = $redirect->getOriginal('from_path');

            if ($originalFromPath && $originalFromPath !== $redirect->from_path) {
                Cache::forget('redirect:'.ltrim($originalFromPath, '/'));
            }
        });

        static::deleted(fn (self $redirect) => Cache::forget('redirect:'.ltrim($redirect->from_path, '/')));
    }
}
