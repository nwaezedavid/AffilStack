<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An affiliate program (a brand) on one of the customer's connected
 * networks: "joined" ones can become an AffilStack product with the
 * network's tracked link; "available" ones show with a link to apply on
 * the network. Refreshed by every sync.
 */
#[Fillable(['network_connection_id', 'external_id', 'name', 'advertiser_name', 'status', 'homepage_url', 'tracking_url', 'logo_url', 'category', 'currency', 'commission', 'description', 'deep_linking', 'meta', 'last_seen_at'])]
class NetworkProgram extends Model
{
    protected function casts(): array
    {
        return [
            'deep_linking' => 'boolean',
            'meta' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(NetworkConnection::class, 'network_connection_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function isJoined(): bool
    {
        return $this->status === 'joined';
    }
}
