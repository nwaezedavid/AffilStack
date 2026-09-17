<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'price_monthly_cents', 'price_yearly_cents',
    'currency', 'credits_per_month', 'active_products_limit', 'contact_limit',
    'team_seats', 'seat_mode', 'channels', 'features', 'is_featured', 'is_active', 'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

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
}
