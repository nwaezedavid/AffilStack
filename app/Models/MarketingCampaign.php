<?php

namespace App\Models;

use Database\Factories\MarketingCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ad campaign Brain (the Marketing Agent) has drafted for an offer —
 * see BrainAgentService. `brief` holds Brain's draft: ad copy variants,
 * targeting suggestions, a budget range, and the image prompts used to
 * generate `image_urls`.
 */
#[Fillable([
    'offer_id', 'created_by_id', 'title', 'goal', 'status', 'brief', 'image_urls',
    'meta_campaign_ref', 'launch_transcript', 'approved_by_id', 'approved_at',
    'last_optimized_at', 'last_optimization_summary', 'failure_reason',
])]
class MarketingCampaign extends Model
{
    /** @use HasFactory<MarketingCampaignFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RUNNING = 'running';

    public const STATUS_OPTIMIZING = 'optimizing';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PAUSED = 'paused';

    /**
     * Statuses BrainAgentService::optimizeRunningCampaigns() picks up —
     * both "just launched" and "already being optimized" are eligible for
     * the next optimization pass.
     */
    public const OPTIMIZABLE_STATUSES = [self::STATUS_RUNNING, self::STATUS_OPTIMIZING];

    protected function casts(): array
    {
        return [
            'brief' => 'array',
            'image_urls' => 'array',
            'approved_at' => 'datetime',
            'last_optimized_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::OPTIMIZABLE_STATUSES, true);
    }
}
