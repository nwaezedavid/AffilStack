<?php

namespace App\Models;

use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer who builds add-ons for the marketplace (feature 7).
 */
#[Fillable(['user_id', 'display_name', 'slug', 'website', 'support_email', 'bio', 'status', 'revenue_share_percent', 'terms_accepted_at'])]
class DeveloperProfile extends Model
{
    protected function casts(): array
    {
        return [
            'revenue_share_percent' => 'decimal:2',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(DeveloperEarning::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * The developer's share of each sale, in percent.
     */
    public function sharePercent(): float
    {
        return $this->revenue_share_percent !== null
            ? (float) $this->revenue_share_percent
            : MarketplaceSettings::revenueSharePercent();
    }
}
