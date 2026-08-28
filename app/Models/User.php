<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'company_name', 'country', 'credits_balance', 'is_suspended', 'notify_email_on_completion', 'referral_code'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, LogsActivity, Notifiable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
            'notify_email_on_completion' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_suspended'])
            ->logOnlyDirty();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin') || $this->hasRole('support');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['trialing', 'active'])
            ->latestOfMany();
    }

    /**
     * Whether the user's current plan includes a given content channel (e.g.
     * "youtube", "ugc", "pinterest", "google_maps"). Plans without an active
     * subscription have no channels at all — this is the enforcement point
     * for Plan::$channels, which until now was only ever displayed, never
     * checked.
     */
    public function canUseChannel(string $channel): bool
    {
        return in_array($channel, $this->activeSubscription?->plan?->channels ?? [], true);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    public function crmContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    public function creditLedger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function trackedLinks(): HasMany
    {
        return $this->hasMany(TrackedLink::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * As the referrer: every user this account has referred.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralClicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    /**
     * As the referred user: the single Referral row crediting whoever sent
     * them, if any. Most users have none.
     */
    public function referredBy(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_user_id');
    }

    /**
     * Lazily generate and persist this user's referral code on first use,
     * the same "create on first real need" pattern as
     * LinkCloakingService::getOrCreateForOffer() — most users never open
     * the Referrals page, so most users never get one.
     */
    public function referralCode(): string
    {
        if ($this->referral_code) {
            return $this->referral_code;
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        $this->update(['referral_code' => $code]);

        return $code;
    }

    protected function referralLink(): Attribute
    {
        return Attribute::get(fn () => url('/r/'.$this->referralCode()));
    }
}
