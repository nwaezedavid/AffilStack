<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent 04: proof of a cookie choice (see ConsentController). Append-only:
 * the latest row for a browser/user is the choice currently in force.
 */
#[Fillable(['consent_id', 'visitor_id', 'user_id', 'analytics', 'marketing', 'action', 'source', 'policy_version', 'ip_hash', 'browser', 'device_type', 'page_path', 'gpc', 'created_at'])]
class ConsentRecord extends Model
{
    use HasFactory;

    public const ACTIONS = [
        'accept_all' => 'Accepted all',
        'reject_all' => 'Rejected all',
        'custom' => 'Saved custom choices',
        'withdraw' => 'Withdrew consent',
        'signal' => 'Browser privacy signal (GPC/DNT)',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'analytics' => 'boolean',
            'marketing' => 'boolean',
            'gpc' => 'boolean',
            'created_at' => 'datetime',
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

    /**
     * The choice currently in force for a signed-in user (their most recent
     * record on any device), or null if they never made one.
     */
    public static function latestForUser(int $userId): ?self
    {
        return static::query()->where('user_id', $userId)->orderByDesc('id')->first();
    }
}
