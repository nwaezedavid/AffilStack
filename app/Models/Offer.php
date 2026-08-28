<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'product_name', 'product_url', 'affiliate_network', 'status',
    'ideal_customer_summary', 'where_to_find', 'recommended_channel',
    'recommended_angle', 'research_data',
])]
class Offer extends Model
{
    protected function casts(): array
    {
        return [
            'research_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
