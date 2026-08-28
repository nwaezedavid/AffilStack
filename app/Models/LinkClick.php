<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tracked_link_id', 'ip_address', 'user_agent', 'device_type', 'browser', 'country', 'referrer', 'clicked_at'])]
class LinkClick extends Model
{
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function trackedLink(): BelongsTo
    {
        return $this->belongsTo(TrackedLink::class);
    }
}
