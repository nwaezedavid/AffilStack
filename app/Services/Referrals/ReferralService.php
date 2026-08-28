<?php

namespace App\Services\Referrals;

use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as CookieValue;

/**
 * Feature 12 (built-in referral program). Deliberately designed so a user's
 * referral relationship can be exported to PartnerStack later without a
 * rewrite: a referral's lifecycle (signed_up -> converted) and its
 * commission ledger (ReferralEvent, one row per commissionable payment)
 * are already split into the same shape PartnerStack expects — a
 * "partnership" plus a stream of "commission" events — so migrating means
 * mapping these tables to their API, not redesigning the data model.
 *
 * Two hook points, both inside PaymentProcessor because that is the one
 * place first payments AND renewals both flow through:
 *  - completeSignup()     -> createReferralForNewUser()
 *  - activateSubscription() -> recordCommission()
 */
class ReferralService
{
    public function resolveReferrerFromCookie(Request $request): ?User
    {
        $code = $request->cookie(config('referrals.cookie_name'));

        if (! $code) {
            return null;
        }

        return User::where('referral_code', $code)->first();
    }

    /**
     * Called from the public /r/{code} redirect. Never blocks the redirect
     * on failure — logging a click is a nice-to-have, not the critical path.
     */
    public function recordClick(User $referrer, Request $request): void
    {
        $referrer->referralClicks()->create([
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'clicked_at' => now(),
        ]);
    }

    public function referralCookie(User $referrer): CookieValue
    {
        return Cookie::make(
            config('referrals.cookie_name'),
            $referrer->referralCode(),
            60 * 24 * (int) config('referrals.cookie_days'),
        );
    }

    /**
     * Called from RegistrationController::store() right after the
     * PendingSignup is created. Guards against a user referring themselves
     * (same email as the cookie's owner) — anything more sophisticated
     * (device fingerprinting, etc.) is out of scope for v1.
     */
    public function attachReferrerToPendingSignup(PendingSignup $pending, Request $request): void
    {
        $referrer = $this->resolveReferrerFromCookie($request);

        if (! $referrer || strcasecmp($referrer->email, $pending->email) === 0) {
            return;
        }

        $pending->update(['referred_by_user_id' => $referrer->id]);
    }

    /**
     * Called from PaymentProcessor::completeSignup() once the new User
     * exists. Idempotent via firstOrCreate keyed on referred_user_id, since
     * a webhook/callback race can call this twice for the same signup.
     */
    public function createReferralForNewUser(PendingSignup $pending, User $newUser): void
    {
        if (! $pending->referred_by_user_id) {
            return;
        }

        Referral::firstOrCreate(
            ['referred_user_id' => $newUser->id],
            ['referrer_id' => $pending->referred_by_user_id, 'status' => 'signed_up'],
        );
    }

    /**
     * Called from PaymentProcessor::activateSubscription() for every
     * successful subscription payment — first payment or renewal alike.
     * A no-op when the paying user has no referrer, which is the common
     * case. The commission itself is recorded as "pending" — an admin
     * approves and marks it paid from the Filament referral resource.
     */
    public function recordCommission(Subscription $subscription, PaymentTransaction $transaction): void
    {
        $referral = Referral::where('referred_user_id', $transaction->user_id)->first();

        if (! $referral) {
            return;
        }

        $isFirstEvent = ! $referral->events()->exists();

        $referral->events()->create([
            'payment_transaction_id' => $transaction->id,
            'event_type' => $isFirstEvent ? 'first_payment' : 'renewal',
            'amount_cents' => (int) round($transaction->amount_cents * (float) config('referrals.commission_rate')),
            'currency' => $transaction->currency,
            'status' => 'pending',
            'occurred_at' => now(),
        ]);

        if ($isFirstEvent) {
            $referral->update(['status' => 'converted', 'converted_at' => now()]);
        }
    }
}
