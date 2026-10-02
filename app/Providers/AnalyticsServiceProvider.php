<?php

namespace App\Providers;

use App\Models\CrmCampaign;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Analytics\AnalyticsDataEraser;
use App\Services\Analytics\AnalyticsHooks;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Agent 04: wiring for cookie consent + first-party analytics, kept in one
 * provider so it never touches another feature's files:
 *
 * - The consent and visitor-id cookies are written by the banner script in
 *   the browser (so they work on cached marketing pages), which means they
 *   are plain first-party values — they must be excluded from Laravel's
 *   cookie encryption or the server would read them as tampered and drop
 *   them.
 * - Beacon rate limits (per IP).
 * - Server-side tracking hooks on existing model/framework events (see
 *   AnalyticsHooks), and erasing a user's analytics data when their
 *   account is permanently deleted.
 */
class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        EncryptCookies::except([
            (string) config('analytics.cookies.consent'),
            (string) config('analytics.cookies.visitor'),
        ]);

        RateLimiter::for('analytics-events', fn (Request $request) => Limit::perMinute((int) config('analytics.rate_limits.events_per_minute', 60))->by('analytics-events:'.$request->ip()));
        RateLimiter::for('analytics-consent', fn (Request $request) => Limit::perMinute((int) config('analytics.rate_limits.consent_per_minute', 20))->by('analytics-consent:'.$request->ip()));

        Event::listen(Login::class, [AnalyticsHooks::class, 'login']);
        Event::listen(RequestHandled::class, [AnalyticsHooks::class, 'requestHandled']);

        Offer::created(fn (Offer $offer) => app(AnalyticsHooks::class)->offerCreated($offer));
        Generation::saved(fn (Generation $generation) => app(AnalyticsHooks::class)->generationSaved($generation));
        CrmCampaign::updated(fn (CrmCampaign $campaign) => app(AnalyticsHooks::class)->campaignUpdated($campaign));
        PaymentTransaction::created(fn (PaymentTransaction $transaction) => app(AnalyticsHooks::class)->transactionCreated($transaction));
        PaymentTransaction::updated(fn (PaymentTransaction $transaction) => app(AnalyticsHooks::class)->transactionUpdated($transaction));

        User::forceDeleted(fn (User $user) => app(AnalyticsDataEraser::class)->eraseForUser($user));
    }
}
