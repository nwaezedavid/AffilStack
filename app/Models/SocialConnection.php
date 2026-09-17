<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's own connected third-party social account — LinkedIn (task #3,
 * identity-only, never posting) today; YouTube/TikTok/Instagram (task #2)
 * reuse this same model rather than one-off tables per platform. See
 * App\Contracts\SocialOAuthProvider for the interface every provider's
 * OAuth service implements.
 */
#[Fillable(['user_id', 'provider', 'credentials', 'account_name', 'account_id', 'connected_at'])]
class SocialConnection extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }
}
