<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent 04: one visit (see config('analytics.session_timeout_minutes')).
 */
#[Fillable([
    'visitor_id', 'user_id', 'started_at', 'last_activity_at', 'duration_seconds', 'engaged_seconds',
    'pageviews', 'events', 'landing_path', 'exit_path', 'channel', 'source', 'medium', 'campaign',
    'term', 'content', 'referrer_host', 'click_id_type', 'device_type', 'browser', 'os', 'language',
    'country', 'city',
])]
class AnalyticsSession extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(AnalyticsVisitor::class, 'visitor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class, 'session_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'analytics_session_id');
    }

    public function isBounce(): bool
    {
        return $this->pageviews <= 1 && $this->events === 0 && $this->engaged_seconds < 10;
    }
}
