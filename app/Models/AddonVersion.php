<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One version of an add-on's listing and settings. Customers only ever see
 * an approved version; each change goes through admin review.
 */
#[Fillable([
    'addon_id', 'version', 'status', 'name', 'tagline', 'description', 'category', 'icon_path', 'screenshots',
    'pricing', 'price_cents', 'website_url', 'support_url', 'privacy_url', 'install_url', 'redirect_uris', 'scopes',
    'webhook_url', 'webhook_events', 'content', 'changelog', 'review_note', 'submitted_at', 'reviewed_at', 'reviewed_by_id',
])]
class AddonVersion extends Model
{
    protected function casts(): array
    {
        return [
            'screenshots' => 'array',
            'redirect_uris' => 'array',
            'scopes' => 'array',
            'webhook_events' => 'array',
            'content' => 'array',
            'price_cents' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function isPaid(): bool
    {
        return $this->pricing === 'monthly' && $this->price_cents > 0;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function priceLabel(): string
    {
        return $this->isPaid() ? '$'.number_format($this->price_cents / 100, 2).'/month' : 'Free';
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }

    /**
     * @return array<int, string>
     */
    public function screenshotUrls(): array
    {
        return array_map(fn (string $path) => Storage::disk('public')->url($path), $this->screenshots ?? []);
    }

    /**
     * @return array<string, string> scope => what it allows, in the customer's words
     */
    public function scopeDescriptions(): array
    {
        $all = config('marketplace.scopes');

        return collect($this->scopes ?? [])->filter(fn ($scope) => isset($all[$scope]))->mapWithKeys(fn ($scope) => [$scope => $all[$scope]])->all();
    }

    public function categoryLabel(): string
    {
        return (string) config('marketplace.categories.'.$this->category, $this->category);
    }
}
