<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code handed to the official AffilStack app after the customer
 * signs in on app.affilstack.com; the app swaps it (with its PKCE
 * verifier) for tokens within a few minutes. See MobileAuthServer.
 */
#[Fillable(['user_id', 'code_hash', 'redirect_uri', 'code_challenge', 'expires_at', 'used_at'])]
class MobileAuthorizationCode extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
