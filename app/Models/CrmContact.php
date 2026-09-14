<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'name', 'company', 'title', 'email', 'phone', 'website',
    'source', 'location', 'notes', 'status', 'raw_data',
    'unsubscribe_token', 'unsubscribed_at',
])]
class CrmContact extends Model
{
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailSends(): HasMany
    {
        return $this->hasMany(CrmEmailSend::class);
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    /**
     * Lazily generated and persisted on first use, mirroring
     * User::referralCode() — most contacts are never emailed, so most rows
     * never need one.
     */
    public function unsubscribeToken(): string
    {
        if ($this->unsubscribe_token) {
            return $this->unsubscribe_token;
        }

        do {
            $token = Str::random(32);
        } while (static::where('unsubscribe_token', $token)->exists());

        $this->update(['unsubscribe_token' => $token]);

        return $token;
    }
}
