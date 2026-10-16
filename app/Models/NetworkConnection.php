<?php

namespace App\Models;

use App\Services\Networks\NetworkRegistry;
use Database\Factories\NetworkConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's account on an affiliate network (Impact, PartnerStack,
 * Awin, CJ, ClickBank, Digistore24). The credentials are the keys the
 * customer created in that network, stored encrypted. "pending" is only
 * used while a Digistore24 approval is waiting; "error" means the last
 * sync was refused (usually revoked keys) and the customer should check
 * the connection.
 */
#[Fillable(['user_id', 'network', 'credentials', 'account_id', 'account_name', 'status', 'connect_token', 'last_synced_at', 'programs_synced_at', 'last_error', 'last_sync_new', 'last_sync_updated'])]
#[Hidden(['credentials', 'connect_token'])]
class NetworkConnection extends Model
{
    /** @use HasFactory<NetworkConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'connect_token' => 'encrypted',
            'last_synced_at' => 'datetime',
            'programs_synced_at' => 'datetime',
            'last_sync_new' => 'integer',
            'last_sync_updated' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(NetworkProgram::class);
    }

    /**
     * @param  Builder<NetworkConnection>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', ['connected', 'error']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['connected', 'error'], true);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return ($this->credentials ?? [])[$key] ?? $default;
    }

    public function label(): string
    {
        return NetworkRegistry::label($this->network);
    }
}
