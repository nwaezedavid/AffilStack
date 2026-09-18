<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

    /**
     * Neither the create/edit form nor the "create redirect from this 404"
     * quick action stopped an admin from pointing a redirect at itself, or
     * at a chain of existing redirects that loops back to where it started
     * — either one sends every visitor of that path into an infinite
     * ERR_TOO_MANY_REDIRECTS with no warning ever surfaced. Walks the
     * proposed to_path's own redirect chain (bounded, so a long-but-real
     * chain of legitimate redirects can never make this hang) and reports
     * true only if it ever leads back to $fromPath. A to_path pointing to a
     * full external URL (a different host) can never cycle back through
     * this app's own redirect table, so it's never flagged.
     */
    public static function wouldCreateCycle(string $fromPath, string $toPath, ?int $ignoreId = null): bool
    {
        $from = ltrim(trim($fromPath), '/');
        $next = static::normalizeInternalPath($toPath);

        if ($next === null) {
            return false;
        }

        $visited = [$from];
        $hops = 0;

        while ($next !== null && $hops < 25) {
            if ($next === $from) {
                return true;
            }

            if (in_array($next, $visited, true)) {
                // A cycle exists somewhere in the chain, but not one that
                // loops back to $from specifically — not this save's fault.
                return false;
            }

            $visited[] = $next;

            $row = static::where('from_path', $next)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->first();

            if (! $row) {
                return false;
            }

            $next = static::normalizeInternalPath($row->to_path);
            $hops++;
        }

        return false;
    }

    /**
     * A relative path ("new-page" / "/new-page") normalizes to its bare
     * form; a full URL on THIS app's own host normalizes to its path
     * component (so a redirect chain can still be followed through one);
     * a full URL on any other host returns null, since it leaves this
     * app's own redirect table entirely and can never contribute to a
     * cycle within it.
     */
    protected static function normalizeInternalPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $ownHost = parse_url(url('/'), PHP_URL_HOST);
            $targetHost = parse_url($path, PHP_URL_HOST);

            if (! $targetHost || ! $ownHost || strcasecmp($targetHost, $ownHost) !== 0) {
                return null;
            }

            $path = parse_url($path, PHP_URL_PATH) ?: '/';
        }

        return ltrim($path, '/');
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
