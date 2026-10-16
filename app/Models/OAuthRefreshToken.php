<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lets a connected app get a new access token without asking the customer
 * again. Each use replaces it with a new one.
 */
#[Fillable(['addon_install_id', 'token_hash', 'expires_at', 'revoked_at'])]
class OAuthRefreshToken extends Model
{
    protected $table = 'oauth_refresh_tokens';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function install(): BelongsTo
    {
        return $this->belongsTo(AddonInstall::class, 'addon_install_id');
    }
}
