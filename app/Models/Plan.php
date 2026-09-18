<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'name', 'slug', 'description', 'price_monthly_cents', 'price_yearly_cents',
    'currency', 'credits_per_month', 'active_products_limit', 'contact_limit',
    'team_seats', 'seat_mode', 'channels', 'features', 'is_featured', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Audit item #7 (caching/performance) — the pricing page, the signup
     * form, and the billing page all load the same handful of active plans
     * on every single request, and plans change only when an admin edits
     * one from Filament. Cached indefinitely and busted from booted()
     * below, same pattern as SiteSetting::get()/set().
     *
     * Caches plain attribute arrays, never the Eloquent models/Collection
     * themselves — a persistent cache store (database/file/Redis) serializes
     * with PHP's native serialize(), which can hand back an unusable
     * __PHP_Incomplete_Class for a hydrated Model on the next request. Raw
     * arrays always round-trip safely, and hydrate() rebuilds real Plan
     * models from them exactly as if they'd just been queried.
     *
     * @return Collection<int, self>
     */
    public static function activePublicList(): Collection
    {
        $rows = Cache::rememberForever('plans:active_public_list', function () {
            return static::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (self $plan) => $plan->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('plans:active_public_list'));
        static::deleted(fn () => Cache::forget('plans:active_public_list'));
    }

    /**
     * Item #2 of the pricing/payments audit: annual billing is always
     * exactly 15% cheaper than paying monthly for a year. Centralized here
     * so PlansSeeder and the admin Plan form compute it the same way
     * instead of two independently-typed numbers that can drift apart.
     */
    public const ANNUAL_DISCOUNT_RATE = 0.15;

    public static function yearlyPriceCentsFor(int $monthlyCents): int
    {
        return (int) round($monthlyCents * 12 * (1 - self::ANNUAL_DISCOUNT_RATE));
    }

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'features' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * "shared" (the Business tier) means every seat on the account sees
     * and works every offer, with User::seat_role deciding publish rights
     * — see the plans.seat_mode migration for the full isolated/shared
     * contrast.
     */
    public function isSharedTeamPlan(): bool
    {
        return $this->seat_mode === 'shared';
    }

    public function priceMonthly(): float
    {
        return $this->price_monthly_cents / 100;
    }

    public function priceYearly(): float
    {
        return $this->price_yearly_cents / 100;
    }

    /**
     * Human-readable labels for every value the `channels` array can hold
     * — the single source of truth for both the admin Plan form's
     * checkbox list and the public pricing page's "everything included"
     * breakdown, so the two can never drift into showing different names
     * for the same channel key.
     *
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            'research' => 'Offer research',
            'blog' => 'Blog / Medium articles',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'ugc' => 'UGC',
            'pinterest' => 'Pinterest',
            'google_maps' => 'Google Maps CRM',
            'x' => 'X (Twitter)',
            'tiktok' => 'TikTok',
        ];
    }
}
