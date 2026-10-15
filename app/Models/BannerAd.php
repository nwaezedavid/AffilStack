<?php

namespace App\Models;

use Database\Factories\BannerAdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An auto-updating banner ad. Partners embed the banner script or iframe
 * once; whatever ads are live here (headline, message, discount, image,
 * theme, landing page) are what their visitors see, with the current
 * logo from Brand Settings. Ad keys are "ad{id}-{size}" in the banner
 * catalog, so views and clicks are counted like every other banner.
 */
#[Fillable(['name', 'headline', 'message', 'cta_label', 'discount_badge', 'discount_note', 'image_path', 'theme', 'landing_path', 'sizes', 'weight', 'is_active', 'starts_at', 'ends_at', 'sort_order'])]
class BannerAd extends Model
{
    /** @use HasFactory<BannerAdFactory> */
    use HasFactory;

    /**
     * Sizes an ad is drawn for: the web display sizes partners embed.
     *
     * @var array<int, string>
     */
    public const SIZES = ['300x250', '336x280', '728x90', '970x250', '300x600', '160x600', '320x50', '320x100'];

    protected function casts(): array
    {
        return [
            'sizes' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function themes(): array
    {
        return [
            'navy' => 'Navy',
            'blue' => 'Blue',
            'gold' => 'Gold',
            'emerald' => 'Emerald',
            'light' => 'Light',
        ];
    }

    /**
     * Ads running right now: switched on and inside their schedule.
     *
     * @param  Builder<BannerAd>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isLive(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /**
     * @return array<int, string>
     */
    public function availableSizes(): array
    {
        $sizes = array_values(array_intersect(self::SIZES, (array) ($this->sizes ?: [])));

        return $sizes !== [] ? $sizes : self::SIZES;
    }

    public function supportsSize(string $size): bool
    {
        return in_array($size, $this->availableSizes(), true);
    }

    public function bannerKey(string $size): string
    {
        return 'ad'.$this->id.'-'.$size;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Off',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Scheduled',
            $this->ends_at !== null && $this->ends_at->lte(now()) => 'Ended',
            default => 'Live',
        };
    }
}
