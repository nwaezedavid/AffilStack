<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API roadmap item #1 — one row per Idempotency-Key a caller has used on a
 * write endpoint (see EnsureIdempotency). Records the exact response that
 * was returned the first time so a retried request with the same key gets
 * back byte-for-byte the same result instead of creating a second record.
 * Only successful (2xx) responses are ever stored — see EnsureIdempotency's
 * own docblock for why a failed attempt is deliberately left retryable.
 */
#[Fillable(['user_id', 'key', 'route', 'request_hash', 'response_status', 'response_body'])]
class IdempotencyKey extends Model
{
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
