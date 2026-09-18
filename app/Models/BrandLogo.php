<?php

namespace App\Models;

use Database\Factories\BrandLogoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * One logo in the "brands we've worked with" homepage marquee — an
 * unlimited, admin-managed list (Filament: Content > Brand Logos). See
 * marketing/home.blade.php for the looping carousel this drives.
 */
#[Fillable(['name', 'logo_path', 'url', 'sort_order', 'is_active'])]
class BrandLogo extends Model
{
    /** @use HasFactory<BrandLogoFactory> */
    use HasFactory;

    /**
     * Cached indefinitely and busted on save/delete — same pattern as
     * HomepageFeature::previewAwareActiveList()/Plan::activePublicList().
     *
     * @return Collection<int, self>
     */
    public static function activePublicList(): Collection
    {
        $rows = Cache::rememberForever('brand_logos:active_list', function () {
            return static::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (self $logo) => $logo->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('brand_logos:active_list'));
        static::deleted(fn () => Cache::forget('brand_logos:active_list'));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function logoUrl(): string
    {
        return Storage::disk('public')->url($this->logo_path);
    }
}
