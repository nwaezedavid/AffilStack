<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Feature 1 (branded link cloaking & click analytics): a cloaked /go/{code}
 * link standing in for one offer's real destination URL, scoped to a single
 * content channel ("module") so clicks can be broken down per channel. Every
 * content-generation module that embeds the "{{AFFILIATE_LINK}}" placeholder
 * gets one of these lazily, via Offer::cloak() — see that method and
 * LinkCloakingService.
 */
#[Fillable(['user_id', 'offer_id', 'module', 'code', 'destination_url', 'clicks_count'])]
class TrackedLink extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    protected function shortUrl(): Attribute
    {
        return Attribute::get(fn () => url('/go/'.$this->code));
    }
}
