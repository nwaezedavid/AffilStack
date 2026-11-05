<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id', 'plan_id', 'status', 'billing_cycle', 'gateway', 'gateway_customer_id',
    'gateway_subscription_id', 'trial_ends_at', 'current_period_start',
    'current_period_end', 'cancel_at_period_end', 'canceled_at', 'renewal_reminder_sent_at', 'last_credit_grant_at',
    'pending_plan_id', 'pending_billing_cycle', 'assigned_by_admin', 'assigned_by_user_id', 'payment_method_id',
    'trial_reminder_sent_at', 'renewal_attempted_at',
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
            'assigned_by_admin' => 'boolean',
            'trial_reminder_sent_at' => 'datetime',
            'renewal_attempted_at' => 'datetime',
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

    /**
     * The admin who gave this complimentary month, when assigned_by_admin.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * The saved card a renewal or trial conversion charges after the
     * customer wallet — falls back to the user's default card when unset.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function trialInvite(): HasOne
    {
        return $this->hasOne(TrialInvite::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /**
     * Stripe subscriptions created through Stripe Checkout renew themselves
     * and report back by webhook; everything else (one-time gateways,
     * admin-assigned months, wallet payments, trial conversions) is renewed
     * by AffilStack itself.
     */
    public function renewsThroughStripe(): bool
    {
        return $this->gateway === 'stripe' && filled($this->gateway_subscription_id);
    }

    public function renewalPriceCents(): int
    {
        return $this->billing_cycle === 'yearly' ? (int) $this->plan?->price_yearly_cents : (int) $this->plan?->price_monthly_cents;
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
