<?php

namespace App\Models;

use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * "The testimonial section will appear as soon as I have a minimum of 3
 * updated in the admin dashboard area." See published() for that gate and
 * marketing/home.blade.php for the homepage section it drives.
 *
 * A testimonial an admin authors directly (user_id null) keeps working
 * exactly as before — is_published is the only lever, status just defaults
 * to 'approved' since there's no review step for the admin's own content.
 * One a customer submits from their dashboard (see TestimonialController)
 * always starts life as status=pending/is_published=false; the Approve/
 * Decline row actions on TestimonialsTable are what flip status (and, for
 * Approve, is_published too) from there. See STATUS_* below.
 */
#[Fillable(['user_id', 'author_name', 'author_role', 'avatar_path', 'quote', 'rating', 'sort_order', 'is_published', 'status'])]
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

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

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

    /**
     * Null for a testimonial an admin authored directly — see the class
     * docblock. Only set for one a customer submitted from their own
     * dashboard.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
