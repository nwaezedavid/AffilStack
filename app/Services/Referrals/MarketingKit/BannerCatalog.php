<?php

namespace App\Services\Referrals\MarketingKit;

use App\Models\AffiliateBanner;
use App\Models\BannerAd;
use Illuminate\Support\Collection;

/**
 * Every banner an affiliate can use: live auto-updating banner ads first,
 * then admin uploads, then the built-in designs. Each banner has a stable
 * key — "{design}-{size}" for built-ins, "upload{id}-{size}" for uploads,
 * "ad{id}-{size}" for banner ads — that is
 * passed as "b" on the tracked /r/{code} link and used to group impression
 * and click stats.
 */
class BannerCatalog
{
    public const KEY_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*-\d{2,4}x\d{2,4}$/';

    /**
     * @var Collection<int, AffiliateBanner>|null
     */
    private ?Collection $uploads = null;

    /**
     * @var Collection<int, BannerAd>|null
     */
    private ?Collection $ads = null;

    /**
     * @return array<string, array{width: int, height: int, label: string, group: string}>
     */
    public function sizes(): array
    {
        return config('affiliate_kit.sizes');
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function designs(): array
    {
        return config('affiliate_kit.designs');
    }

    public function isSize(string $size): bool
    {
        return array_key_exists($size, $this->sizes());
    }

    public function isDesign(string $design): bool
    {
        return array_key_exists($design, $this->designs());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(?string $size = null): Collection
    {
        $uploads = $this->uploads()
            ->when($size, fn (Collection $items) => $items->where('size', $size))
            ->map(fn (AffiliateBanner $banner): array => $this->describeUpload($banner))
            ->values();

        $builtIn = collect($this->sizes())
            ->keys()
            ->when($size, fn (Collection $keys) => $keys->filter(fn (string $key): bool => $key === $size))
            ->flatMap(fn (string $sizeKey) => collect(array_keys($this->designs()))->map(fn (string $design): array => $this->describeBuiltIn($design, $sizeKey)))
            ->values();

        $ads = $this->liveAds()
            ->flatMap(fn (BannerAd $ad) => collect($ad->availableSizes())
                ->filter(fn (string $sizeKey): bool => $this->isSize($sizeKey) && ($size === null || $size === $sizeKey))
                ->map(fn (string $sizeKey): array => $this->describeAd($ad, $sizeKey)))
            ->values();

        return $ads->concat($uploads)->concat($builtIn)->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        if (! preg_match(self::KEY_PATTERN, $key)) {
            return null;
        }

        [$design, $size] = $this->split($key);

        if (! $this->isSize($size)) {
            return null;
        }

        if (preg_match('/^ad(\d+)$/', $design, $match)) {
            $ad = $this->liveAds()->firstWhere('id', (int) $match[1]) ?? BannerAd::find((int) $match[1]);

            return $ad && $ad->supportsSize($size) ? $this->describeAd($ad, $size) : null;
        }

        if (preg_match('/^upload(\d+)$/', $design, $match)) {
            $upload = $this->uploads()->first(fn (AffiliateBanner $banner): bool => $banner->id === (int) $match[1] && $banner->size === $size);

            return $upload ? $this->describeUpload($upload) : null;
        }

        return $this->isDesign($design) ? $this->describeBuiltIn($design, $size) : null;
    }

    public function isValidKey(string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * The banner a rotating (or explicitly chosen) embed shows. An explicit,
     * valid design wins; otherwise admin uploads for that size are preferred,
     * falling back to a random built-in design.
     *
     * @return array<string, mixed>
     */
    public function pick(string $size, ?string $design, bool $allowInactiveAd = false): array
    {
        if ($design && ! in_array($design, ['rotate', 'ads'], true) && ($chosen = $this->find($design.'-'.$size))) {
            if ($chosen['kind'] !== 'ad' || $chosen['is_live'] || $allowInactiveAd) {
                return $chosen;
            }
        }

        $ads = $this->liveAds()->filter(fn (BannerAd $ad): bool => $ad->supportsSize($size))->values();

        if ($ads->isNotEmpty()) {
            return $this->describeAd($this->weightedPick($ads), $size);
        }

        $uploads = $this->uploads()->where('size', $size);

        if ($uploads->isNotEmpty()) {
            return $this->describeUpload($uploads->random());
        }

        return $this->describeBuiltIn(array_rand($this->designs()), $size);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $key): array
    {
        $position = strrpos($key, '-');

        return [substr($key, 0, $position), substr($key, $position + 1)];
    }

    /**
     * @param  Collection<int, BannerAd>  $ads
     */
    private function weightedPick(Collection $ads): BannerAd
    {
        $total = $ads->sum(fn (BannerAd $ad): int => max(1, $ad->weight));
        $roll = random_int(1, max(1, $total));

        foreach ($ads as $ad) {
            $roll -= max(1, $ad->weight);

            if ($roll <= 0) {
                return $ad;
            }
        }

        return $ads->first();
    }

    /**
     * @return Collection<int, BannerAd>
     */
    private function liveAds(): Collection
    {
        return $this->ads ??= BannerAd::query()->live()->orderBy('sort_order')->orderByDesc('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function describeAd(BannerAd $ad, string $size): array
    {
        $dimensions = $this->sizes()[$size];
        $preview = route('embed.banner', ['size' => $size, 'design' => 'ad'.$ad->id, 'preview' => 1]);

        return [
            'key' => $ad->bannerKey($size),
            'kind' => 'ad',
            'design' => 'ad'.$ad->id,
            'design_name' => $ad->headline,
            'size' => $size,
            'size_label' => $dimensions['label'],
            'group' => $dimensions['group'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'title' => $ad->headline.' · '.str_replace('x', '×', $size),
            'alt' => $ad->headline,
            'preview_url' => $preview,
            'svg_url' => null,
            'png_url' => null,
            'image_url' => null,
            'landing_path' => $ad->landing_path ?: '/pricing',
            'is_live' => $ad->isLive(),
            'ad' => $ad,
        ];
    }

    /**
     * @return Collection<int, AffiliateBanner>
     */
    private function uploads(): Collection
    {
        return $this->uploads ??= AffiliateBanner::query()
            ->where('is_active', true)
            ->whereIn('size', array_keys($this->sizes()))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function describeBuiltIn(string $design, string $size): array
    {
        $dimensions = $this->sizes()[$size];
        $pngAvailable = PngBannerRenderer::available();
        $svgUrl = route('affiliate-banners.image', ['design' => $design, 'size' => $size, 'format' => 'svg']);
        $pngUrl = $pngAvailable ? route('affiliate-banners.image', ['design' => $design, 'size' => $size, 'format' => 'png']) : null;
        $config = $this->designs()[$design];

        return [
            'key' => $design.'-'.$size,
            'kind' => 'builtin',
            'design' => $design,
            'design_name' => $config['name'],
            'size' => $size,
            'size_label' => $dimensions['label'],
            'group' => $dimensions['group'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'title' => $config['name'].' · '.str_replace('x', '×', $size),
            'alt' => 'AffilStack: '.$config['headline'],
            'preview_url' => $svgUrl,
            'svg_url' => $svgUrl,
            'png_url' => $pngUrl,
            'image_url' => $pngUrl ?? $svgUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeUpload(AffiliateBanner $banner): array
    {
        $dimensions = $this->sizes()[$banner->size];
        $url = $banner->imageUrl();

        return [
            'key' => $banner->bannerKey(),
            'kind' => 'upload',
            'design' => 'upload'.$banner->id,
            'design_name' => $banner->title,
            'size' => $banner->size,
            'size_label' => $dimensions['label'],
            'group' => $dimensions['group'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'title' => $banner->title.' · '.str_replace('x', '×', $banner->size),
            'alt' => $banner->alt_text ?: 'Try AffilStack',
            'preview_url' => $url,
            'svg_url' => null,
            'png_url' => null,
            'download_url' => $url,
            'image_url' => $url,
        ];
    }
}
