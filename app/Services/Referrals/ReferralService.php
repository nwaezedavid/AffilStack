<?php

namespace App\Services\Referrals;

use App\Models\PaymentTransaction;
use App\Models\PendingSignup;
use App\Models\Referral;
use App\Models\ReferralCampaign;
use App\Models\ReferralEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ReferralProgram;
use App\Support\SiteDomains;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
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
     * $bannerKey/$source come from the Marketing Kit's tracked links ("b" and
     * utm_medium) so per-banner and per-channel clicks can be reported.
     */
    public function recordClick(User $referrer, Request $request, ?string $bannerKey = null, ?string $source = null, ?ReferralCampaign $campaign = null): void
    {
        $referrer->referralClicks()->create([
            'referral_campaign_id' => $campaign?->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'banner_key' => $bannerKey,
            'source' => $source,
            'clicked_at' => now(),
        ]);
    }

    /**
     * Set on the parent domain when the site is split across subdomains:
     * the click lands on affilstack.com, the sign-up happens on
     * app.affilstack.com, and both must see the same cookie.
     */
    public function referralCookie(User $referrer): CookieValue
    {
        return Cookie::make(
            config('referrals.cookie_name'),
            $referrer->referralCode(),
            60 * 24 * ReferralProgram::cookieDays(),
            '/',
            SiteDomains::sharedCookieDomain(),
        );
    }

    /**
     * Which campaign link brought the visitor, or a cookie that forgets an
     * older campaign when the latest click came through a plain link.
     */
    public function campaignCookie(?ReferralCampaign $campaign): CookieValue
    {
        $name = (string) config('referrals.campaign_cookie_name');

        if ($campaign === null) {
            return Cookie::forget($name, '/', SiteDomains::sharedCookieDomain());
        }

        return Cookie::make($name, (string) $campaign->id, 60 * 24 * ReferralProgram::cookieDays(), '/', SiteDomains::sharedCookieDomain());
    }

    public function resolveCampaignFromCookie(Request $request, User $referrer): ?ReferralCampaign
    {
        $id = $request->cookie((string) config('referrals.campaign_cookie_name'));

        if (! is_string($id) || ! ctype_digit($id)) {
            return null;
        }

        return ReferralCampaign::whereKey((int) $id)->where('user_id', $referrer->id)->first();
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

        $pending->update([
            'referred_by_user_id' => $referrer->id,
            'referral_campaign_id' => $this->resolveCampaignFromCookie($request, $referrer)?->id,
        ]);
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
            ['referrer_id' => $pending->referred_by_user_id, 'status' => 'signed_up', 'referral_campaign_id' => $pending->referral_campaign_id],
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
        $rate = $referral->referrer ? ReferralProgram::rateFor($referral->referrer) : (float) config('referrals.commission_rate');

        $referral->events()->create([
            'payment_transaction_id' => $transaction->id,
            'event_type' => $isFirstEvent ? 'first_payment' : 'renewal',
            'amount_cents' => (int) round($transaction->amount_cents * $rate),
            'currency' => $transaction->currency,
            'status' => 'pending',
            'occurred_at' => now(),
        ]);

        if ($isFirstEvent) {
            $referral->update(['status' => 'converted', 'converted_at' => now()]);
        }
    }

    /**
     * Called from RefundProcessor when a payment tied to a referral
     * commission is refunded or charged back (audit gap #6 — previously
     * there was no clawback path at all). A commission that hasn't yet been
     * committed to any payout is simply voided — nothing to claw back. One
     * that's already paid, or already claimed by a payout request awaiting
     * processing, is left alone historically (a "paid" event should keep
     * meaning a real payout batch went out — see ReferralEvent) and instead
     * offset by a negative ledger entry that nets out of the referrer's
     * next payout, the same commission ledger every other event lives in.
     */
    public function reverseCommission(PaymentTransaction $transaction, string $reason): void
    {
        $event = ReferralEvent::where('payment_transaction_id', $transaction->id)
            ->whereNotIn('status', ['reversed', 'rejected'])
            ->whereNotIn('event_type', ['refund', 'chargeback'])
            ->first();

        if (! $event) {
            return;
        }

        if ($event->status !== 'paid' && $event->referral_payout_id === null) {
            $event->update(['status' => 'reversed']);

            return;
        }

        ReferralEvent::create([
            'referral_id' => $event->referral_id,
            'payment_transaction_id' => $transaction->id,
            'event_type' => $reason === 'charged_back' ? 'chargeback' : 'refund',
            'amount_cents' => -$event->amount_cents,
            'currency' => $event->currency,
            'status' => 'approved',
            'occurred_at' => now(),
        ]);
    }
}
