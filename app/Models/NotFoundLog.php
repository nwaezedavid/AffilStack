<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A deduped, capped log of paths that 404'd on the public marketing site —
 * RankMath's "404 Monitor". Deliberately NOT an unbounded event log: each
 * distinct path is one row, incremented on repeat hits, so a bot scanning
 * thousands of random paths can't grow this table without bound. See
 * RedirectFallbackController for where this is written.
 */
class NotFoundLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * Rows this table will ever hold at once — once at the cap, a brand
     * new (never-seen) path is silently dropped rather than logged. An
     * existing path already being tracked still increments normally; the
     * cap only stops the table from growing further under something like a
     * bot randomly scanning URLs.
     */
    protected const MAX_ROWS = 500;

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public static function record(string $path, ?string $referer): void
    {
        $existing = static::where('path', $path)->first();

        if ($existing) {
            $existing->increment('hits_count');
            $existing->update(['last_seen_at' => now(), 'referer' => $referer ?: $existing->referer]);

            return;
        }

        // At the cap, make room by dropping the stalest entry rather than
        // refusing new ones — otherwise one burst of bot junk would stop the
        // monitor from ever recording a real broken link again.
        if (static::count() >= self::MAX_ROWS) {
            static::query()->orderBy('last_seen_at')->limit(1)->delete();
        }

        // The check-then-act above isn't atomic: two concurrent first-ever
        // hits on the same brand-new path (a bot firing parallel requests,
        // or a browser prefetch racing the real navigation) can both reach
        // here having seen no existing row, and the `path` column's unique
        // index then rejects the loser's insert. That's an expected race,
        // not a real error — treat it exactly like "someone already logged
        // this a moment ago" and increment instead of letting a 404 visitor
        // see a 500.
        try {
            static::create([
                'path' => $path,
                'referer' => $referer,
                'hits_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            static::where('path', $path)->first()?->increment('hits_count', 1, ['last_seen_at' => now()]);
        }
    }
}
