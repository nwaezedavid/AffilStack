<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * An add-on in the marketplace: a connected app, a template pack or an
 * automation recipe. What customers see comes from its live (approved)
 * version; the developer edits a draft version and submits it for review.
 */
#[Fillable(['developer_profile_id', 'slug', 'type', 'status', 'live_version_id', 'client_id', 'client_secret_hash', 'webhook_secret', 'is_featured', 'installs_count', 'published_at'])]
#[Hidden(['client_secret_hash', 'webhook_secret'])]
class Addon extends Model
{
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'webhook_secret' => 'encrypted',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class, 'developer_profile_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AddonVersion::class);
    }

    public function liveVersion(): BelongsTo
    {
        return $this->belongsTo(AddonVersion::class, 'live_version_id');
    }

    /**
     * The version the developer is working on (draft, submitted or
     * rejected), if any.
     */
    public function workingVersion(): HasOne
    {
        return $this->hasOne(AddonVersion::class)->whereIn('status', ['draft', 'submitted', 'rejected'])->latestOfMany();
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(DeveloperEarning::class);
    }

    public function installs(): HasMany
    {
        return $this->hasMany(AddonInstall::class);
    }

    /**
     * @param  Builder<Addon>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('status', 'published')
            ->whereNotNull('live_version_id')
            ->whereHas('developer', fn (Builder $developer) => $developer->where('status', 'active'));
    }

    public function isListed(): bool
    {
        return $this->status === 'published' && $this->live_version_id !== null && $this->developer?->isActive();
    }

    /**
     * The version a customer gets: the live one. The developer gets their
     * latest version, so they can try an app before it's approved.
     */
    public function versionFor(?User $user): ?AddonVersion
    {
        if ($user !== null && $this->developer?->user_id === $user->id) {
            return $this->workingVersion()->first() ?? $this->liveVersion;
        }

        return $this->liveVersion;
    }

    public function isApp(): bool
    {
        return $this->type === 'app';
    }

    public function typeLabel(): string
    {
        return (string) config('marketplace.types.'.$this->type.'.label', $this->type);
    }

    /**
     * New "Connect with AffilStack" credentials; the secret is shown once.
     */
    public function issueClientCredentials(): string
    {
        $secret = 'afsec_'.Str::random(48);

        $this->forceFill([
            'client_id' => $this->client_id ?? 'afapp_'.Str::lower(Str::random(24)),
            'client_secret_hash' => hash('sha256', $secret),
            'webhook_secret' => $this->webhook_secret ?? 'whsec_'.Str::random(40),
        ])->save();

        return $secret;
    }

    public function secretMatches(string $secret): bool
    {
        return $this->client_secret_hash !== null && hash_equals($this->client_secret_hash, hash('sha256', $secret));
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'add-on';
        $base = Str::limit($base, 70, '');
        $slug = in_array($base, ['installed', 'create'], true) ? $base.'-add-on' : $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
