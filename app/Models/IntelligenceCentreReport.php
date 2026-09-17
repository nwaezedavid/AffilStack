<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task #7 (Intelligence Centre): the latest AI self-assessment of a user's
 * account — one row per user, replaced on every regeneration rather than
 * kept as history, matching the single-row-per-user pattern used elsewhere
 * (e.g. EmailConnection). See App\Services\Intelligence\IntelligenceCentreService,
 * which builds `metrics` (the raw numbers fed to the AI) and `assessment`
 * (the AI's structured self-assessment output) independently, so the raw
 * numbers are always available even if the AI's reasoning about them looks
 * off in hindsight.
 */
#[Fillable(['user_id', 'metrics', 'assessment', 'generated_at'])]
class IntelligenceCentreReport extends Model
{
    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'assessment' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function healthScore(): int
    {
        return (int) ($this->assessment['health_score'] ?? 0);
    }

    public function upgradeRecommended(): bool
    {
        return (bool) ($this->assessment['upgrade_recommended'] ?? false);
    }

    public function suggestedPlan(): ?Plan
    {
        $slug = $this->assessment['suggested_plan_slug'] ?? null;

        return $slug ? Plan::where('slug', $slug)->first() : null;
    }
}
