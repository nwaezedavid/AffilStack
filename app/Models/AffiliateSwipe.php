<?php

namespace App\Models;

use Database\Factories\AffiliateSwipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Promo copy affiliates can copy straight out of their Marketing Kit: an
 * email (subject + body), a social post (per platform), or a text link
 * (body = anchor text). Placeholders are filled per affiliate by
 * MarketingKit::renderSwipe(): {affiliate_link}, {first_name} (the
 * recipient — left as a merge tag for the affiliate's email tool) and
 * {your_name} (the affiliate).
 */
#[Fillable(['preset_key', 'type', 'title', 'subject', 'body', 'platform', 'is_active', 'sort_order'])]
class AffiliateSwipe extends Model
{
    /** @use HasFactory<AffiliateSwipeFactory> */
    use HasFactory;

    public const TYPES = [
        'email' => 'Email swipe',
        'social' => 'Social post',
        'text_link' => 'Text link',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
