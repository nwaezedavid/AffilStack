<?php

namespace App\Services\Referrals\MarketingKit;

use App\Models\AffiliateBanner;
use Illuminate\Support\Collection;

/**
 * Every banner an affiliate can use: admin uploads first (they take
 * priority), then the built-in designs. Each banner has a stable key —
 * "{design}-{size}" for built-ins, "upload{id}-{size}" for uploads — that is
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

        return $uploads->concat($builtIn)->values();
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
    public function pick(string $size, ?string $design): array
    {
        if ($design && $design !== 'rotate' && ($chosen = $this->find($design.'-'.$size))) {
            return $chosen;
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
