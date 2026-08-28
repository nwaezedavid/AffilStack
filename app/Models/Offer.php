<?php

namespace App\Models;

use App\Services\Links\LinkCloakingService;
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

    public function trackedLinks(): HasMany
    {
        return $this->hasMany(TrackedLink::class);
    }

    /**
     * Replace the "{{AFFILIATE_LINK}}" placeholder AI-generated content uses
     * with this offer's cloaked /go/ link for the given content channel —
     * lazily creating that link (feature 1) the first time it's needed, so
     * an offer with no generated content yet never gets one. Text without
     * the placeholder passes through untouched (and without creating a link).
     */
    public function cloak(?string $text, string $module = 'general'): string
    {
        if ($text === null || ! str_contains($text, '{{AFFILIATE_LINK}}')) {
            return $text ?? '';
        }

        $link = app(LinkCloakingService::class)->getOrCreateForOffer($this, $module);

        return str_replace('{{AFFILIATE_LINK}}', $link->short_url, $text);
    }
}
