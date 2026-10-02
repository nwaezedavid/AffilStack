<?php

namespace App\Services\Referrals\MarketingKit;

use App\Models\AffiliateBannerImpression;
use App\Models\AffiliateSwipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything the Marketing Kit tab set needs per affiliate: tracked links
 * (always /r/{code}, so the existing 60-day attribution cookie and
 * commission flow apply unchanged), banner embed snippets, swipe copy with
 * placeholders filled in, and per-banner / per-channel stats.
 */
class MarketingKit
{
    public const SOURCES = ['banner', 'email', 'social', 'text_link', 'link'];

    public function __construct(private BannerCatalog $catalog) {}

    /**
     * @param  array<string, string>  $extra
     */
    public function trackedLink(User $user, string $medium, ?string $content = null, array $extra = []): string
    {
        $query = array_filter(['utm_source' => 'affiliate', 'utm_medium' => $medium, 'utm_content' => $content] + $extra);

        return url('/r/'.$user->referralCode()).'?'.http_build_query($query);
    }

    public function bannerLink(User $user, string $bannerKey): string
    {
        return $this->trackedLink($user, 'banner', $bannerKey, ['b' => $bannerKey]);
    }

    /**
     * The three copy-paste embed options for one banner, with the affiliate's
     * code already in place.
     *
     * @param  array<string, mixed>  $banner
     * @return array{html: string, iframe: string, script: string, script_rotating: string}
     */
    public function snippets(User $user, array $banner): array
    {
        $code = $user->referralCode();
        $alt = e($banner['alt']);
        $link = e($this->bannerLink($user, $banner['key']));
        $width = $banner['width'];
        $height = $banner['height'];
        $iframeSrc = e(route('embed.banner', ['size' => $banner['size'], 'ref' => $code, 'design' => $banner['design']]));
        $script = e(route('embed.banner-script'));

        return [
            'html' => "<a href=\"{$link}\" target=\"_blank\" rel=\"sponsored noopener\"><img src=\"".e($banner['image_url'])."\" width=\"{$width}\" height=\"{$height}\" alt=\"{$alt}\" style=\"max-width:100%;height:auto;border:0\"></a>",
            'iframe' => "<iframe src=\"{$iframeSrc}\" width=\"{$width}\" height=\"{$height}\" title=\"AffilStack\" loading=\"lazy\" scrolling=\"no\" sandbox=\"allow-popups allow-popups-to-escape-sandbox\" style=\"border:0;max-width:100%;height:auto;aspect-ratio:{$width}/{$height}\"></iframe>",
            'script' => "<script async src=\"{$script}\" data-ref=\"{$code}\" data-size=\"{$banner['size']}\" data-design=\"{$banner['design']}\"></script>",
            'script_rotating' => "<script async src=\"{$script}\" data-ref=\"{$code}\" data-size=\"{$banner['size']}\"></script>",
        ];
    }

    /**
     * A swipe with every placeholder filled for this affiliate. {first_name}
     * is the *recipient's* name, so it's left as a token the dashboard
     * swaps for the affiliate's email-tool merge tag.
     *
     * @return array{id: int, type: string, title: string, subject: ?string, body: string, platform: ?string, link: string}
     */
    public function renderSwipe(AffiliateSwipe $swipe, User $user): array
    {
        $content = $swipe->preset_key ?: 'swipe-'.$swipe->id;
        $medium = $swipe->type === 'text_link' ? 'text_link' : $swipe->type;
        $link = $this->trackedLink($user, $medium, $swipe->platform ? $swipe->platform : $content);
        $replace = [
            '{affiliate_link}' => $link,
            '{your_name}' => Str::of((string) $user->name)->trim()->before(' ')->value() ?: 'Your name',
        ];

        return [
            'id' => $swipe->id,
            'type' => $swipe->type,
            'title' => $swipe->title,
            'subject' => $swipe->subject !== null ? strtr($swipe->subject, $replace) : null,
            'body' => strtr($swipe->body, $replace),
            'platform' => $swipe->platform,
            'link' => $link,
        ];
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function swipesFor(User $user): Collection
    {
        return AffiliateSwipe::query()->active()->get()
            ->map(fn (AffiliateSwipe $swipe): array => $this->renderSwipe($swipe, $user))
            ->groupBy('type');
    }

    /**
     * Counts one embed impression, at most once per viewer per banner within
     * the dedupe window. The viewer is identified only by a hash of IP + user
     * agent held in the cache — nothing about them is stored.
     */
    public function recordImpression(User $user, string $bannerKey, Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        if ($userAgent === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|monitor/i', $userAgent)) {
            return false;
        }

        $fingerprint = sha1($request->ip().'|'.$userAgent.'|'.$user->id.'|'.$bannerKey);

        if (! Cache::add('affiliate-banner-impression:'.$fingerprint, true, now()->addMinutes(config('affiliate_kit.impression_dedupe_minutes')))) {
            return false;
        }

        $attributes = ['user_id' => $user->id, 'banner_key' => $bannerKey, 'day' => now()->toDateString()];
        $updated = AffiliateBannerImpression::query()->where($attributes)->increment('impressions');

        if ($updated === 0) {
            AffiliateBannerImpression::query()->insertOrIgnore($attributes + ['impressions' => 0, 'created_at' => now(), 'updated_at' => now()]);
            AffiliateBannerImpression::query()->where($attributes)->increment('impressions');
        }

        return true;
    }

    /**
     * @return array{clicks: int, clicks_30d: int, signups: int, converted: int, by_source: array<string, int>, banners: Collection<string, array{impressions: int, clicks: int, ctr: float|null}>}
     */
    public function stats(User $user): array
    {
        $clicks = $user->referralClicks();

        $bySource = $user->referralClicks()
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->mapWithKeys(fn ($total, $source): array => [($source ?: 'link') => (int) $total]);

        $bannerClicks = $user->referralClicks()
            ->whereNotNull('banner_key')
            ->select('banner_key', DB::raw('count(*) as total'))
            ->groupBy('banner_key')
            ->pluck('total', 'banner_key');

        $impressions = $user->affiliateBannerImpressions()
            ->select('banner_key', DB::raw('sum(impressions) as total'))
            ->groupBy('banner_key')
            ->pluck('total', 'banner_key');

        $banners = $impressions->keys()->merge($bannerClicks->keys())->unique()
            ->mapWithKeys(function (string $key) use ($impressions, $bannerClicks): array {
                $shown = (int) ($impressions[$key] ?? 0);
                $clicked = (int) ($bannerClicks[$key] ?? 0);

                return [$key => ['impressions' => $shown, 'clicks' => $clicked, 'ctr' => $shown > 0 ? round($clicked / $shown * 100, 2) : null]];
            })
            ->sortByDesc(fn (array $row): int => $row['clicks'] * 100000 + $row['impressions']);

        return [
            'clicks' => (clone $clicks)->count(),
            'clicks_30d' => (clone $clicks)->where('clicked_at', '>=', now()->subDays(30))->count(),
            'signups' => $user->referrals()->count(),
            'converted' => $user->referrals()->where('status', 'converted')->count(),
            'by_source' => $bySource->all(),
            'banners' => $banners,
        ];
    }
}
