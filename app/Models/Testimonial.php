<?php

namespace App\Models;

use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * "The testimonial section will appear as soon as I have a minimum of 3
 * updated in the admin dashboard area." See published() for that gate and
 * marketing/home.blade.php for the homepage section it drives.
 */
#[Fillable(['author_name', 'author_role', 'avatar_path', 'quote', 'rating', 'sort_order', 'is_published'])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * Minimum published testimonials before the homepage section appears —
     * a page with one or two feels sparse rather than reassuring, so it
     * stays hidden until there's a real, credible set. Below this
     * threshold, published() returns an empty collection even if some
     * exist, exactly like it doesn't exist yet.
     */
    public const MINIMUM_TO_DISPLAY = 3;

    /**
     * Cached indefinitely and busted on save/delete — same pattern as
     * BrandLogo::activePublicList()/Plan::activePublicList(). Returns an
     * empty collection below MINIMUM_TO_DISPLAY so callers never need to
     * know the threshold exists — just check isNotEmpty().
     *
     * @return Collection<int, self>
     */
    public static function published(): Collection
    {
        $rows = Cache::rememberForever('testimonials:published_list', function () {
            $published = static::where('is_published', true)->orderBy('sort_order')->get();

            if ($published->count() < self::MINIMUM_TO_DISPLAY) {
                return [];
            }

            return $published->map(fn (self $testimonial) => $testimonial->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('testimonials:published_list'));
        static::deleted(fn () => Cache::forget('testimonials:published_list'));
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }
}
