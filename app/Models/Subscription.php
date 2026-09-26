<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'plan_id', 'status', 'billing_cycle', 'gateway', 'gateway_customer_id',
    'gateway_subscription_id', 'trial_ends_at', 'current_period_start',
    'current_period_end', 'cancel_at_period_end', 'canceled_at', 'renewal_reminder_sent_at', 'last_credit_grant_at',
    'pending_plan_id', 'pending_billing_cycle',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'renewal_reminder_sent_at' => 'datetime',
            'last_credit_grant_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * A scheduled downgrade (audit item #2) — set by PlanChangeService,
     * applied once the current period actually ends.
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trialing', 'active'])
            && (! $this->current_period_end || $this->current_period_end->isFuture());
    }
}
