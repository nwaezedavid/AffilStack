<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'google_id', 'password', 'company_name', 'country', 'credits_balance', 'is_suspended', 'notify_email_on_completion', 'referral_code', 'payout_method', 'payout_details', 'agency_owner_id', 'seat_offer_id', 'seat_role', 'refund_policy_accepted_at', 'is_affiliate_only', 'api_wallet_balance_cents', 'api_wallet_auto_recharge_enabled', 'api_wallet_auto_recharge_threshold_cents', 'api_wallet_auto_recharge_amount_cents', 'api_wallet_payment_method_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'app_authentication_secret', 'app_authentication_recovery_codes', 'payout_details'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'refund_policy_accepted_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
            'is_affiliate_only' => 'boolean',
            'api_wallet_auto_recharge_enabled' => 'boolean',
            'notify_email_on_completion' => 'boolean',
            'last_active_at' => 'datetime',
            'payout_details' => 'encrypted:array',
            'deleted_at' => 'datetime',
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
        if ($this->is_suspended) {
            return false;
        }

        return $this->hasRole('admin') || $this->hasRole('support') || $this->hasRole('admin_sub');
    }

    /**
     * The only role allowed to approve an AI agent's permission request
     * (Tom's fixes, Tony's codebase changes) — deliberately separate from
     * 'admin' so an admin sub-account never inherits this authority.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Full, unscoped panel access — every Filament resource/page department
     * check (see App\Filament\Concerns\ScopedToDepartment) bypasses for
     * these two roles. Admin sub-accounts ('admin_sub') are never full
     * admins — their access is exactly what their department permissions
     * grant, nothing more.
     */
    public function isFullAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super-admin');
    }

    public function isAdminSubAccount(): bool
    {
        return $this->hasRole('admin_sub');
    }

    /**
     * Whether this user's panel access covers a given department (see
     * config('admin.departments')) — full admins always do; an admin
     * sub-account only does when explicitly granted that department's
     * permission (see AdminSubAccountResource).
     */
    public function canAccessDepartment(string $department): bool
    {
        return $this->isFullAdmin() || $this->getAllPermissions()->contains('name', "department.{$department}");
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
     * An account created by AffiliateApplicationService::approve() — never
     * a platform customer, and never bought a plan. Scoped by
     * RestrictAffiliateOnlyAccounts to referrals/profile/logout only — see
     * config('referrals.affiliate_only_allowed_routes'). Every OTHER user
     * is already an affiliate automatically (User::referralCode() /
     * /referrals is open to any logged-in account); this flag exists only
     * to identify the accounts that exist for NOTHING ELSE.
     */
    public function isAffiliateOnly(): bool
    {
        return (bool) $this->is_affiliate_only;
    }

    /**
     * Whether the account this user bills to (itself, or its agency owner
     * for a seat) is on a "shared" (Business tier) plan — see
     * Plan::isSharedTeamPlan() and the plans.seat_mode migration. True for
     * both the owner and every seat on such a plan; always false for an
     * "isolated" plan (every plan except Business, unchanged).
     */
    public function onSharedTeamPlan(): bool
    {
        return $this->billableUser()->activeSubscription?->plan?->isSharedTeamPlan() ?? false;
    }

    /**
     * A seat (not the owner) on a shared plan — the one case where
     * Offer::isAccessibleBy() grants access to every offer on the account
     * rather than just one assigned via seat_offer_id.
     */
    public function hasSharedTeamAccess(): bool
    {
        return $this->isSeat() && $this->onSharedTeamPlan();
    }

    /**
     * A shared-plan seat with the "manager" role — the only seat that can
     * publish content or mark a DM sequence started (see
     * ContentCalendarController); every other seat, on any plan, is
     * draft-only, matching the original agency-seat backlog wording.
     */
    public function isTeamManager(): bool
    {
        return $this->hasSharedTeamAccess() && $this->seat_role === 'manager';
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

    /**
     * The offers this user should actually see and work: on a shared plan
     * (Business tier), that's every offer on the billable account — the
     * whole point of that tier being real team collaboration rather than
     * agency-style one-offer-per-seat isolation. Otherwise (every other
     * plan, and an isolated-plan seat, which still only reaches its one
     * assigned offer via Offer::isAccessibleBy()) it's just this user's
     * own offers, identical to calling offers() directly.
     */
    public function visibleOffers(): HasMany
    {
        return $this->onSharedTeamPlan() ? $this->billableUser()->offers() : $this->offers();
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    /**
     * The generations this user's content calendar/dashboard should show —
     * see visibleOffers() for the same isolated/shared distinction. On a
     * shared plan this is every generation across the whole team's offers
     * (so the owner and every manager/member see one shared calendar), not
     * just what this particular login created.
     */
    public function visibleGenerations(): Builder
    {
        if (! $this->onSharedTeamPlan()) {
            return $this->generations();
        }

        return Generation::query()->whereIn('offer_id', $this->billableUser()->offers()->pluck('id'));
    }

    public function crmContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    /**
     * This user's own connected sender for CRM nurture emails (task #1) —
     * never team-shared, matching crmContacts() above. Null means CRM
     * nurture sends still go out through AffilStack's own shared mailer —
     * see CrmEmailService.
     */
    public function emailConnection(): HasOne
    {
        return $this->hasOne(EmailConnection::class);
    }

    /**
     * This user's own connected third-party social accounts (task #3:
     * LinkedIn; task #2: YouTube/TikTok/Instagram) — never team-shared.
     */
    public function socialConnections(): HasMany
    {
        return $this->hasMany(SocialConnection::class);
    }

    /**
     * Task #3: this user's own LinkedIn "paste their reply, get an
     * AI-drafted response" history — see LinkedInReplyAssistantService.
     */
    public function linkedinReplyDrafts(): HasMany
    {
        return $this->hasMany(LinkedinReplyDraft::class);
    }

    /**
     * Task #7: this account's latest Intelligence Centre self-assessment —
     * see IntelligenceCentreReport and IntelligenceCentreService. Owner-only,
     * like CRM/earnings/referrals/billing — a team seat never has one of
     * its own (see config('agency.seat_allowed_routes')).
     */
    public function intelligenceCentreReport(): HasOne
    {
        return $this->hasOne(IntelligenceCentreReport::class);
    }

    /**
     * Plan::contact_limit is 0 for "unlimited" (Pro/Agency) — the same
     * convention the pricing and billing pages already display, just never
     * enforced until the Google Maps lead finder made bulk imports possible.
     */
    public function crmContactLimitReached(): bool
    {
        $limit = $this->activeSubscription?->plan?->contact_limit ?? 0;

        if ($limit === 0) {
            return false;
        }

        return $this->crmContacts()->count() >= $limit;
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

    public function referralPayouts(): HasMany
    {
        return $this->hasMany(ReferralPayout::class);
    }

    /**
     * Audit gap #7 (outbound webhooks) — see App\Services\Webhooks\WebhookDispatcher.
     */
    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    /**
     * Saved payment methods (audit item #2) — see PaymentMethodRecorder.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->latest();
    }

    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first();
    }

    /**
     * The card API wallet auto-recharges bill — deliberately separate from
     * defaultPaymentMethod() above: a user may want their subscription on
     * one saved card and unattended API auto-recharges on another. See
     * ApiWalletManager::attemptAutoRecharge().
     */
    public function apiWalletPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'api_wallet_payment_method_id');
    }

    /**
     * Every signed movement in the API usage prepay wallet (item: API usage
     * fee) — see ApiWalletTransaction and ApiWalletManager, its one choke
     * point. Kept in sync with api_wallet_balance_cents, mirroring how
     * creditLedger()/credits_balance relate.
     */
    public function apiWalletTransactions(): HasMany
    {
        return $this->hasMany(ApiWalletTransaction::class)->latest();
    }

    public function hasPayoutMethodOnFile(): bool
    {
        return filled($this->payout_method) && filled($this->payout_details);
    }

    /**
     * The commission total (in cents, summed across every currency the
     * affiliate has earned in) that's been admin-approved but not yet
     * claimed by a payout request. Kept for API consumers and the
     * Intelligence Centre, which only need a single at-a-glance figure —
     * see unpaidApprovedCommissionByCurrency() for the per-currency
     * breakdown that actually drives payout eligibility (audit gap #5:
     * summing different currencies together here is a display
     * simplification, not something request eligibility relies on).
     */
    public function unpaidApprovedCommissionCents(): int
    {
        return (int) ReferralEvent::whereHas('referral', fn ($query) => $query->where('referrer_id', $this->id))
            ->where('status', 'approved')
            ->whereNull('referral_payout_id')
            ->sum('amount_cents');
    }

    /**
     * Same balance as unpaidApprovedCommissionCents(), broken out per
     * currency — what ReferralPayoutService and the Referrals page use so
     * a mixed-currency affiliate can request a payout for each currency
     * they've earned in, rather than only ever the single largest one.
     *
     * @return Collection<string, int>
     */
    public function unpaidApprovedCommissionByCurrency(): Collection
    {
        return ReferralEvent::whereHas('referral', fn ($query) => $query->where('referrer_id', $this->id))
            ->where('status', 'approved')
            ->whereNull('referral_payout_id')
            ->selectRaw('currency, sum(amount_cents) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (int) $total);
    }

    public function hasOpenPayoutRequest(?string $currency = null): bool
    {
        return $this->referralPayouts()
            ->where('status', 'requested')
            ->when($currency, fn ($query) => $query->where('currency', $currency))
            ->exists();
    }
}
