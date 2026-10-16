<?php

namespace App\Models;

use Database\Factories\WordpressSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's WordPress site linked through the AffilStack Connector
 * plugin. Both sides hold the same secret and sign every request with it
 * (see App\Services\WordPress\ConnectorSignature). "pending" means the
 * customer approved the site but the plugin hasn't collected its secret
 * yet; only "connected" sites receive articles.
 */
#[Fillable(['user_id', 'site_url', 'rest_url', 'name', 'secret', 'status', 'connect_code_hash', 'connect_code_expires_at', 'auto_send', 'seo_plugin', 'wp_version', 'plugin_version', 'connected_at', 'last_seen_at', 'last_error'])]
#[Hidden(['secret', 'connect_code_hash'])]
class WordpressSite extends Model
{
    /** @use HasFactory<WordpressSiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'auto_send' => 'boolean',
            'connect_code_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WordpressDelivery::class);
    }

    /**
     * @param  Builder<WordpressSite>  $query
     */
    public function scopeConnected(Builder $query): void
    {
        $query->where('status', 'connected');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function host(): string
    {
        return (string) parse_url($this->site_url, PHP_URL_HOST);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->host();
    }
}
