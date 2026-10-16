<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code handed to a connected app after the customer clicks
 * "Allow"; the app swaps it for tokens within a few minutes.
 */
#[Fillable(['addon_id', 'user_id', 'code_hash', 'redirect_uri', 'scopes', 'code_challenge', 'expires_at', 'used_at'])]
class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
