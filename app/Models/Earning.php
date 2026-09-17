<?php

namespace App\Models;

use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'tracked_link_id', 'offer_id', 'network', 'source',
    'external_ref', 'amount_cents', 'currency', 'status', 'converted_at', 'notes',
])]
class Earning extends Model
{
    protected function casts(): array
    {
        return [
            'converted_at' => 'date',
        ];
    }

    /**
     * Audit gap #7 (outbound webhooks — "earning.recorded"). Hooked at the
     * model level since earnings are created both one at a time (the
     * dashboard's manual-entry form) and in bulk (CSV import) from the
     * same service — one choke point beats duplicating a dispatch call in
     * both.
     */
    protected static function booted(): void
    {
        static::created(function (Earning $earning) {
            app(WebhookDispatcher::class)->dispatch($earning->user, 'earning.recorded', [
                'earning_id' => $earning->id,
                'amount_cents' => $earning->amount_cents,
                'currency' => $earning->currency,
                'network' => $earning->network,
                'status' => $earning->status,
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trackedLink(): BelongsTo
    {
        return $this->belongsTo(TrackedLink::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
