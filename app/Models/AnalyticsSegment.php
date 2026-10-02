<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent 04: an advertising audience — rules over analytics_profiles (see
 * App\Services\Analytics\SegmentEvaluator). purpose is "target" (show ads
 * to these people) or "exclude" (leave them out of acquisition ads).
 */
#[Fillable(['key', 'name', 'description', 'purpose', 'rules', 'is_preset', 'created_by'])]
class AnalyticsSegment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_preset' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
