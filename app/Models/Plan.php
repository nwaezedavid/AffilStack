<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'price_monthly_cents', 'price_yearly_cents',
    'currency', 'credits_per_month', 'active_products_limit', 'contact_limit',
    'team_seats', 'channels', 'features', 'is_featured', 'is_active', 'sort_order',
])]
class Plan extends Model
{
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

    public function priceMonthly(): float
    {
        return $this->price_monthly_cents / 100;
    }

    public function priceYearly(): float
    {
        return $this->price_yearly_cents / 100;
    }
}
