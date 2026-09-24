<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * First-party analytics: one row per page load, recorded by
 * AnalyticsBeaconController from the client-side beacon in
 * partials/analytics-beacon.blade.php (shared by the marketing and
 * dashboard layouts, so both logged-out and logged-in traffic count).
 * Deliberately NOT reusing PageView-per-request server middleware — a
 * beacon fires from the browser itself, so a CDN/edge cache in front of
 * the app in production can never make this undercount.
 */
#[Fillable(['path', 'source', 'referrer_host', 'user_id', 'session_id', 'view_token', 'duration_seconds'])]
class PageView extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $view): void {
            $view->view_token ??= Str::random(48);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Classifies a visit's traffic source from the raw referrer + the
     * landing page's own query string, in priority order: an explicit UTM
     * tag always wins (the site owner said what this traffic is), then
     * "no referrer at all" is Direct, then same-host is Internal
     * navigation (not a new "visit" source, but still worth knowing), then
     * a short known-domain list buckets Organic Search / Social, and
     * anything else left over is a generic Referral.
     */
    public static function classifySource(?string $referrer, string $queryString, string $appHost): string
    {
        parse_str(ltrim($queryString, '?'), $query);

        if (! empty($query['utm_medium'])) {
            $medium = strtolower((string) $query['utm_medium']);

            return match (true) {
                str_contains($medium, 'cpc') || str_contains($medium, 'paid') => 'Paid',
                str_contains($medium, 'email') => 'Email',
                str_contains($medium, 'social') => 'Social',
                default => ucfirst($medium),
            };
        }

        if (! empty($query['utm_source'])) {
            return 'Campaign: '.Str::title(str_replace(['-', '_'], ' ', (string) $query['utm_source']));
        }

        if (! $referrer) {
            return 'Direct';
        }

        $refHost = strtolower((string) parse_url($referrer, PHP_URL_HOST));

        if (! $refHost) {
            return 'Direct';
        }

        if ($refHost === strtolower($appHost) || str_ends_with($refHost, '.'.strtolower($appHost))) {
            return 'Internal';
        }

        $searchEngines = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.'];
        foreach ($searchEngines as $needle) {
            if (str_contains($refHost, $needle)) {
                return 'Organic Search';
            }
        }

        $socialNetworks = ['facebook.', 'twitter.', 'x.com', 't.co', 'linkedin.', 'instagram.', 'tiktok.', 'pinterest.', 'reddit.'];
        foreach ($socialNetworks as $needle) {
            if (str_contains($refHost, $needle)) {
                return 'Social';
            }
        }

        return 'Referral';
    }

    protected static function botUserAgentPattern(): string
    {
        return '/bot|crawl|spider|slurp|headless|curl|wget|python-requests|facebookexternalhit|preview/i';
    }

    public static function isLikelyBot(?string $userAgent): bool
    {
        return $userAgent === null || $userAgent === '' || preg_match(self::botUserAgentPattern(), $userAgent) === 1;
    }

    /**
     * @return Collection<int, object{date: string, views: int}>
     */
    public static function dailyCounts(Carbon $since): Collection
    {
        return static::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * @return Collection<int, object{path: string, views: int, avg_duration: ?float}>
     */
    public static function popularPaths(Carbon $since, int $limit = 10): Collection
    {
        return static::query()
            ->where('created_at', '>=', $since)
            ->select('path')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('AVG(duration_seconds) as avg_duration')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object{source: string, views: int}>
     */
    public static function bySource(Carbon $since): Collection
    {
        return static::query()
            ->where('created_at', '>=', $since)
            ->select('source')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('source')
            ->orderByDesc('views')
            ->get();
    }

    public static function uniqueVisitors(Carbon $since): int
    {
        return static::query()->where('created_at', '>=', $since)->distinct('session_id')->count('session_id');
    }

    public static function averageDurationSeconds(Carbon $since): ?float
    {
        return static::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');
    }
}
