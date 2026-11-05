<?php

namespace App\Models;

use Database\Factories\MobileDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One signed-in install of the official AffilStack phone app. Holds the
 * hash of its current refresh token (rotated on every refresh; the
 * previous one is kept so a replayed old token can be spotted and the
 * device signed out), its push token for notifications, and what the app
 * reported about itself. Signing a device out deletes its access tokens
 * straight away. See App\Services\Mobile\MobileAuthServer.
 */
#[Fillable(['user_id', 'name', 'platform', 'app_version', 'os_version', 'locale', 'push_provider', 'push_token', 'push_token_hash', 'refresh_token_hash', 'previous_refresh_token_hash', 'refresh_expires_at', 'authenticated_at', 'last_seen_at', 'revoked_at'])]
#[Hidden(['refresh_token_hash', 'previous_refresh_token_hash', 'push_token', 'push_token_hash'])]
class MobileDevice extends Model
{
    /** @use HasFactory<MobileDeviceFactory> */
    use HasFactory;

    public const PLATFORMS = ['ios', 'android', 'web', 'unknown'];

    protected function casts(): array
    {
        return [
            'push_token' => 'encrypted',
            'refresh_expires_at' => 'datetime',
            'authenticated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Keeps push_token_hash in step with the (encrypted) push token, so a
     * token can be looked up without decrypting every row.
     */
    protected static function booted(): void
    {
        static::saving(function (MobileDevice $device): void {
            if (! $device->isDirty('push_token')) {
                return;
            }

            if ($device->push_token === null || $device->push_token === '') {
                $device->forceFill(['push_token' => null, 'push_token_hash' => null, 'push_provider' => null]);

                return;
            }

            $device->forceFill(['push_token_hash' => hash('sha256', $device->push_token)]);
        });
    }

    /**
     * Detaches $pushToken from every device except $except (the phone it
     * now belongs to), so notifications for a previous account stop.
     */
    public static function releasePushToken(string $pushToken, ?MobileDevice $except = null): void
    {
        static::query()
            ->where('push_token_hash', hash('sha256', $pushToken))
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->update(['push_token' => null, 'push_token_hash' => null, 'push_provider' => null]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * @param  Builder<MobileDevice>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Signs this device out: its access tokens stop working immediately
     * and its refresh token can never be used again.
     */
    public function revoke(): void
    {
        $this->apiTokens()->delete();

        $this->forceFill([
            'revoked_at' => $this->revoked_at ?? now(),
            'refresh_token_hash' => null,
            'previous_refresh_token_hash' => null,
            'push_token' => null,
        ])->save();
    }

    /**
     * Signs every phone app install of $user out (password change, "sign
     * out everywhere", account deletion).
     */
    public static function revokeAllFor(User $user): void
    {
        static::query()->where('user_id', $user->id)->active()->get()->each->revoke();
    }
}
