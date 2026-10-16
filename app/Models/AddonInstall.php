<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's copy of an add-on — and, for paid add-ons, their monthly
 * subscription to it.
 */
#[Fillable(['addon_id', 'user_id', 'status', 'scopes', 'price_cents', 'payment_method_id', 'current_period_end', 'cancel_at_period_end', 'renewal_attempted_at', 'installed_at', 'uninstalled_at'])]
class AddonInstall extends Model
{
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'price_cents' => 'integer',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'renewal_attempted_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(OAuthRefreshToken::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(UserTemplate::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @param  Builder<AddonInstall>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereIn('status', ['active', 'past_due']);
    }

    /**
     * Whether the customer can use the add-on right now.
     */
    public function isUsable(): bool
    {
        if (! in_array($this->status, ['active', 'past_due'], true)) {
            return false;
        }

        return $this->price_cents === 0 || $this->current_period_end === null || $this->current_period_end->isFuture()
            || ($this->status === 'past_due' && $this->current_period_end->copy()->addDays((int) config('marketplace.grace_days'))->isFuture());
    }

    public function isPaid(): bool
    {
        return $this->price_cents > 0;
    }
}
