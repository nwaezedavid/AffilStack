<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One article sent (or being sent) to one WordPress site, and the draft
 * it became there.
 */
#[Fillable(['wordpress_site_id', 'generation_id', 'requested_by_id', 'status', 'wp_post_id', 'wp_post_status', 'edit_url', 'preview_url', 'automatic', 'error', 'sent_at'])]
class WordpressDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'automatic' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WordpressSite::class, 'wordpress_site_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
