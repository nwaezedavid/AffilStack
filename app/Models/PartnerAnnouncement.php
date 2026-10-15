<?php

namespace App\Models;

use Database\Factories\PartnerAnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * News the admin posts to every partner (new promos, commission changes,
 * creative drops) — shown in the Partner Portal and optionally emailed.
 */
#[Fillable(['created_by_id', 'title', 'body', 'is_pinned', 'published_at', 'emailed_at'])]
class PartnerAnnouncement extends Model
{
    /** @use HasFactory<PartnerAnnouncementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @param  Builder<PartnerAnnouncement>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<PartnerAnnouncement>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
