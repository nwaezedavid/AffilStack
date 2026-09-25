<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * API roadmap item #7 (per-token usage analytics) — one row per /v1/* call
 * that reached a resolved token, written by LogApiRequest. Deliberately
 * separate from ApiWalletTransaction: this logs every call (including the
 * free ones), that ledger only logs money actually moving.
 */
#[Fillable(['api_token_id', 'user_id', 'route', 'method', 'status_code', 'cost_cents'])]
class ApiRequestLog extends Model
{
    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'api_token_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
