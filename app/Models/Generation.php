<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'offer_id', 'module', 'input', 'output', 'output_meta',
    'credits_spent', 'status', 'error_message',
    'calendar_status', 'scheduled_for', 'published_at',
    'target_market', 'localized_from_id',
])]
class Generation extends Model
{
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output_meta' => 'array',
            'scheduled_for' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * A short, human title for this piece of content in calendar/list
     * views — the same field each module's display block in
     * offers/show.blade.php treats as the headline, falling back to the
     * offer's product name when a module has no title-like field of its own.
     */
    public function calendarTitle(): string
    {
        return match ($this->module) {
            'blog_article' => $this->output_meta['title'] ?? $this->offer?->product_name ?? 'Untitled',
            'linkedin_article' => $this->output_meta['headline'] ?? $this->offer?->product_name ?? 'Untitled',
            'youtube_script' => $this->output_meta['working_title'] ?? $this->offer?->product_name ?? 'Untitled',
            'pinterest_pin' => $this->output_meta['pins'][0]['title'] ?? $this->offer?->product_name ?? 'Untitled',
            default => $this->offer?->product_name ?? 'Untitled',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * The original generation this one was localized from (item 8), if any.
     */
    public function localizedFrom(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'localized_from_id');
    }

    /**
     * Market-localized copies of this generation, if any were made.
     */
    public function localizations(): HasMany
    {
        return $this->hasMany(Generation::class, 'localized_from_id');
    }

    /**
     * Whether $user may view/act on this generation — its creator, or
     * anyone with access to its offer (a team seat scoped to that one
     * offer, or the offer's owner touching a seat's work). Routes through
     * Offer::isAccessibleBy() rather than a raw user_id check so a seat and
     * its owner can both work with content on their shared offer.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->offer !== null && $this->offer->isAccessibleBy($user);
    }
}
