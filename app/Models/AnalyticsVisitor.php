<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Agent 04: an anonymous visitor (the afs_vid cookie — only set after
 * analytics consent), with first-touch (ft_*) and last-touch (lt_*)
 * attribution. Linked to a User on login/signup — see
 * App\Services\Analytics\IdentityStitcher.
 */
#[Fillable([
    'uuid', 'user_id', 'email_sha256', 'email_sha256_google', 'first_seen_at', 'last_seen_at', 'identified_at',
    'ft_channel', 'ft_source', 'ft_medium', 'ft_campaign', 'ft_term', 'ft_content', 'ft_referrer_host', 'ft_landing_path', 'ft_click_id_type', 'ft_click_id', 'ft_at',
    'lt_channel', 'lt_source', 'lt_medium', 'lt_campaign', 'lt_term', 'lt_content', 'lt_referrer_host', 'lt_landing_path', 'lt_click_id_type', 'lt_click_id', 'lt_at',
    'device_type', 'browser', 'os', 'language', 'country', 'city',
    'sessions_count', 'pageviews_count', 'events_count',
])]
class AnalyticsVisitor extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'identified_at' => 'datetime',
            'ft_at' => 'datetime',
            'lt_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AnalyticsSession::class, 'visitor_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class, 'visitor_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'visitor_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(AnalyticsProfile::class, 'visitor_id');
    }

    /**
     * A short, human-friendly handle for the admin UI ("Visitor 3f9a2c").
     */
    public function shortId(): string
    {
        return substr(str_replace('-', '', (string) $this->uuid), 0, 8);
    }
}
