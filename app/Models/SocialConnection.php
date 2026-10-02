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
 *
 * The verification_* columns hold the result of the last live check
 * against the provider (App\Services\Social\SocialConnectionVerifier):
 * 'verified' (the token works), 'failed' (the check errored — possibly
 * temporary) or 'expired' (the token was revoked or can't be refreshed,
 * so the user has to reconnect). Null means never checked yet.
 */
#[Fillable([
    'user_id', 'provider', 'credentials', 'account_name', 'account_id', 'avatar_url', 'granted_scopes',
    'connected_at', 'last_verified_at', 'verification_status', 'verification_message', 'broken_notified_at',
])]
class SocialConnection extends Model
{
    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'granted_scopes' => 'array',
            'connected_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'broken_notified_at' => 'datetime',
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

    /**
     * The <x-ui.status-pill> status for this connection: the last check's
     * result, or plain "connected" when it has never been checked.
     */
    public function displayStatus(): string
    {
        return $this->verification_status ?? 'connected';
    }

    public function isBroken(): bool
    {
        return in_array($this->verification_status, [self::STATUS_FAILED, self::STATUS_EXPIRED], true);
    }

    public function needsReconnect(): bool
    {
        return $this->verification_status === self::STATUS_EXPIRED;
    }
}
