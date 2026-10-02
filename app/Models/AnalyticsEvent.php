<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent 04: a named behaviour event. With visitor/user ids null it is an
 * anonymous aggregate count (recorded when the person hasn't allowed
 * analytics cookies). Never holds raw contact details — see
 * App\Services\Analytics\EventSanitizer.
 */
#[Fillable(['visitor_id', 'session_id', 'user_id', 'name', 'category', 'path', 'properties', 'value_cents', 'currency', 'dedupe_key', 'created_at'])]
class AnalyticsEvent extends Model
{
    use HasFactory;

    public const CATEGORY_ENGAGEMENT = 'engagement';

    public const CATEGORY_CONVERSION = 'conversion';

    public const CATEGORY_FEATURE = 'feature';

    public const CATEGORY_CUSTOM = 'custom';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(AnalyticsVisitor::class, 'visitor_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function label(string $name): string
    {
        return config("analytics.event_labels.{$name}") ?? ucfirst(str_replace('_', ' ', $name));
    }
}
