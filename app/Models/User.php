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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'company_name', 'country', 'credits_balance', 'is_suspended', 'notify_email_on_completion', 'referral_code', 'agency_owner_id', 'seat_offer_id'])]
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
     * checked. Resolved against billableUser() so a team seat (item 10),
     * which has no subscription of its own, sees exactly what its owner's
     * plan unlocks.
     */
    public function canUseChannel(string $channel): bool
    {
        return in_array($channel, $this->billableUser()->activeSubscription?->plan?->channels ?? [], true);
    }

    /**
     * The account that owns this team seat (item 10), if this user is one —
     * see agencyOwner()/seats() below.
     */
    public function agencyOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agency_owner_id');
    }

    /**
     * Every team seat created under this account.
     */
    public function seats(): HasMany
    {
        return $this->hasMany(User::class, 'agency_owner_id');
    }

    /**
     * The single offer a team seat is scoped to — null for a normal
     * (non-seat) account.
     */
    public function seatOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'seat_offer_id');
    }

    public function isSeat(): bool
    {
        return $this->agency_owner_id !== null;
    }

    /**
     * The account that actually pays for this user's actions — itself
     * normally, or its agency owner for a team seat. Single choke point:
     * CreditManager, canUseChannel(), and the credits balance shown in the
     * dashboard sidebar all resolve through this, so a seat never needs its
     * own subscription or credit balance to work correctly, and none of the
     * existing generation services needed to change to support seats at all.
     */
    public function billableUser(): User
    {
        return $this->agencyOwner ?? $this;
    }

    /**
     * How many total seats (including the owner's own) the account's
     * current plan includes — Plan::team_seats, unused until this feature.
     * No active subscription means solo (1): just the owner, no seats.
     */
    public function maxAgencySeats(): int
    {
        return $this->activeSubscription?->plan?->team_seats ?? 1;
    }

    public function agencySeatsRemaining(): int
    {
        return max(0, $this->maxAgencySeats() - 1 - $this->seats()->count());
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

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function researchClips(): HasMany
    {
        return $this->hasMany(ResearchClip::class);
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
