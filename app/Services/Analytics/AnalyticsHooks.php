<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsVisitor;
use App\Models\CrmCampaign;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\User;
use App\Support\Analytics\AudienceHasher;
use App\Support\Analytics\ConsentState;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Str;
use Throwable;

/**
 * Agent 04: the server-side tracking hooks — each method is wired to an
 * existing model event or framework event in AnalyticsServiceProvider, so
 * login, checkout, payment and feature-use tracking needed no change to the
 * controllers/services that own those flows.
 */
class AnalyticsHooks
{
    public function __construct(
        protected ServerEventRecorder $recorder,
        protected AnalyticsTracker $tracker,
        protected IdentityStitcher $stitcher,
    ) {}

    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        try {
            $this->stitcher->stitchFromRequest($event->user, request());
        } catch (Throwable $e) {
            report($e);
        }

        $this->recorder->record($event->user, 'login', AnalyticsEvent::CATEGORY_ENGAGEMENT, ['guard' => $event->guard]);
    }

    public function offerCreated(Offer $offer): void
    {
        if ($offer->is_sandbox) {
            return;
        }

        $this->recorder->record($this->user($offer->user_id), 'research_started', AnalyticsEvent::CATEGORY_FEATURE, array_filter([
            'network' => $offer->affiliate_network ? Str::limit((string) $offer->affiliate_network, 60, '') : null,
        ]) ?: null, dedupeKey: 'research:'.$offer->id);
    }

    public function generationSaved(Generation $generation): void
    {
        $completedNow = $generation->wasRecentlyCreated
            ? $generation->status === 'completed'
            : $generation->wasChanged('status') && $generation->status === 'completed';

        // Product research has its own event (research_started).
        if (! $completedNow || $generation->module === 'research') {
            return;
        }

        $this->recorder->record($this->user($generation->user_id), 'content_generated', AnalyticsEvent::CATEGORY_FEATURE, [
            'module' => Str::limit((string) $generation->module, 40, ''),
        ], dedupeKey: 'generation:'.$generation->id);
    }

    public function campaignUpdated(CrmCampaign $campaign): void
    {
        if (! $campaign->wasChanged('status') || ! in_array($campaign->status, ['sending', 'sent'], true)) {
            return;
        }

        $this->recorder->record($this->user($campaign->user_id), 'campaign_sent', AnalyticsEvent::CATEGORY_FEATURE, [
            'autopilot' => (bool) $campaign->autopilot,
        ], dedupeKey: 'campaign:'.$campaign->id);
    }

    public function requestHandled(RequestHandled $event): void
    {
        $route = $event->request->route()?->getName();
        $action = $route ? (config('analytics.extension_routes')[$route] ?? null) : null;

        if (! $action || $event->response->getStatusCode() >= 400) {
            return;
        }

        $user = $event->request->user();

        $this->recorder->record($user instanceof User ? $user : null, 'extension_used', AnalyticsEvent::CATEGORY_FEATURE, ['action' => $action]);
    }

    public function transactionCreated(PaymentTransaction $transaction): void
    {
        if ($transaction->status === 'pending' && in_array($transaction->type, ['signup', 'subscription'], true)) {
            $this->checkoutStarted($transaction);

            return;
        }

        if ($transaction->status === 'successful') {
            $this->paymentSucceeded($transaction);
        }
    }

    public function transactionUpdated(PaymentTransaction $transaction): void
    {
        if ($transaction->status === 'successful' && ($transaction->wasChanged('status') || $transaction->wasChanged('user_id'))) {
            $this->paymentSucceeded($transaction);
        }
    }

    protected function checkoutStarted(PaymentTransaction $transaction): void
    {
        $properties = array_filter([
            'plan' => $transaction->plan_id ? Plan::query()->whereKey($transaction->plan_id)->value('slug') : null,
            'billing_cycle' => $transaction->billing_cycle,
            'kind' => $transaction->type === 'signup' ? 'new_customer' : 'plan_change',
            'gateway' => $transaction->gateway,
        ]);

        $user = $this->user($transaction->user_id);

        if ($user) {
            $this->recorder->record($user, 'checkout_started', AnalyticsEvent::CATEGORY_CONVERSION, $properties, (int) $transaction->amount_cents, $transaction->currency, 'checkout:'.$transaction->id);

            return;
        }

        // A brand-new customer: not a user yet, so the event (and the hashed
        // checkout email used to stitch them on payment) hangs off their
        // anonymous visitor — but only if this browser allowed analytics.
        try {
            $request = request();
            $consent = ConsentState::fromRequest($request);
            $uuid = $request->cookies->get((string) config('analytics.cookies.visitor'));
            $visitor = $consent->allowsAnalytics() && is_string($uuid) && Str::isUuid($uuid)
                ? AnalyticsVisitor::query()->where('uuid', strtolower($uuid))->first()
                : null;

            if (! $visitor) {
                $this->tracker->storeEvent(null, null, null, 'checkout_started', AnalyticsEvent::CATEGORY_CONVERSION, null, $properties, now(), (int) $transaction->amount_cents, $transaction->currency, 'checkout:'.$transaction->id);

                return;
            }

            $email = $transaction->pendingSignup?->email;

            if ($email) {
                $visitor->forceFill([
                    'email_sha256' => AudienceHasher::metaEmailHash($email),
                    'email_sha256_google' => AudienceHasher::googleEmailHash($email),
                ])->save();
            }

            $this->tracker->storeEvent($visitor, $this->tracker->activeSession($visitor, now()), null, 'checkout_started', AnalyticsEvent::CATEGORY_CONVERSION, null, $properties, now(), (int) $transaction->amount_cents, $transaction->currency, 'checkout:'.$transaction->id);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function paymentSucceeded(PaymentTransaction $transaction): void
    {
        $user = $this->user($transaction->user_id);

        // A signup transaction is marked successful a moment before its
        // user exists; the follow-up update that sets user_id lands here.
        if (! $user) {
            return;
        }

        $properties = array_filter([
            'type' => $transaction->type,
            'plan' => $transaction->plan_id ? Plan::query()->whereKey($transaction->plan_id)->value('slug') : null,
            'billing_cycle' => $transaction->billing_cycle,
            'gateway' => $transaction->gateway,
        ]);

        if ($transaction->pending_signup_id) {
            try {
                if ($hash = AudienceHasher::metaEmailHash($user->email)) {
                    $this->stitcher->stitchByEmailHash($user, $hash);
                }
            } catch (Throwable $e) {
                report($e);
            }

            $this->recorder->record($user, 'signup_completed', AnalyticsEvent::CATEGORY_CONVERSION, $properties, (int) $transaction->amount_cents, $transaction->currency, 'signup:'.$transaction->id);
        }

        $this->recorder->record($user, 'purchase', AnalyticsEvent::CATEGORY_CONVERSION, $properties, (int) $transaction->amount_cents, $transaction->currency, 'purchase:'.$transaction->id);
    }

    protected function user(?int $id): ?User
    {
        return $id ? User::find($id) : null;
    }
}
