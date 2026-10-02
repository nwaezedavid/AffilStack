<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent 04: interest & intent profile (see AnalyticsProfileBuilder).
 */
#[Fillable([
    'user_id', 'visitor_id', 'lifecycle_stage', 'engagement_score', 'first_seen_at', 'last_seen_at',
    'sessions_count', 'pageviews_count', 'feature_events_30d', 'viewed_pricing_at', 'checkout_started_at',
    'paid_at', 'has_active_subscription', 'country', 'device_type', 'ft_channel', 'ft_source', 'ft_campaign',
    'lt_channel', 'lt_source', 'lt_campaign', 'interests', 'marketing_consent', 'computed_at',
])]
class AnalyticsProfile extends Model
{
    use HasFactory;

    /**
     * Ordered: a profile is in the furthest stage it has reached.
     *
     * @var array<string, string>
     */
    public const STAGES = [
        'visitor' => 'Visitor',
        'engaged' => 'Engaged visitor',
        'pricing_viewer' => 'Viewed pricing',
        'checkout_started' => 'Started checkout',
        'customer' => 'New customer',
        'active' => 'Active customer',
        'at_risk' => 'At-risk customer',
        'churned' => 'Churned',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'viewed_pricing_at' => 'datetime',
            'checkout_started_at' => 'datetime',
            'paid_at' => 'datetime',
            'computed_at' => 'datetime',
            'has_active_subscription' => 'boolean',
            'marketing_consent' => 'boolean',
            'interests' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(AnalyticsVisitor::class, 'visitor_id');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->lifecycle_stage] ?? ucfirst((string) $this->lifecycle_stage);
    }
}
