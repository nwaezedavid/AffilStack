<?php

namespace App\Providers;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\AI\AIProvider;
use App\Services\AI\OpenAIProvider;
use App\Services\AI\RoutingAIProvider;
use App\Support\SignedInHint;
use App\Support\SiteDomains;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenAIProvider::class, fn () => new OpenAIProvider(
            apiKey: (string) config('ai.openai.api_key'),
            baseUrl: (string) config('ai.openai.base_url'),
            textModel: (string) config('ai.openai.text_model'),
            imageModel: (string) config('ai.openai.image_model'),
            timeout: (int) config('ai.openai.timeout'),
        ));

        // Text goes to the engine picked in Admin → AI Agents → AI Engine
        // (OpenAI or Claude, with automatic fallback); images use OpenAI.
        $this->app->singleton(AIProvider::class, fn ($app) => new RoutingAIProvider($app->make(OpenAIProvider::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The customer app on its own subdomain (APP_DOMAIN): tell the public
        // website a visitor is signed in, and let passkeys created on the
        // app host verify there. See SiteDomains.
        // Partners sign in on the affiliate host and never use the
        // customer app, so their sign-in doesn't flip the website's
        // "Dashboard" button (and their sign-out doesn't clear it).
        Event::listen(Login::class, function (Login $event) {
            if (! ($event->user instanceof User && $event->user->isAffiliateOnly())) {
                SignedInHint::set();
            }
        });
        Event::listen(Logout::class, function (Logout $event) {
            if (! ($event->user instanceof User && $event->user->isAffiliateOnly())) {
                SignedInHint::clear();
            }
        });

        if ($appUrl = SiteDomains::appUrl()) {
            // Signing out of the app lands on the public homepage.
            config(['fortify.redirects.logout' => rtrim((string) config('app.url'), '/').'/']);

            config(['passkeys.allowed_origins' => array_values(array_unique([
                ...(array) config('passkeys.allowed_origins', []),
                $appUrl,
            ]))]);
        }

        // API roadmap item #3 — every /v1/* token used to share one flat
        // 60/min limit (routes/api.php's old 'throttle:60,1') regardless of
        // the caller's plan. Keyed by token id when one resolved (so two
        // tokens on the same account never share a bucket), falling back to
        // IP only for the pre-auth case (an invalid/missing bearer token).
        // See config('api_billing.rate_limits').
        //
        // Deliberately re-resolves the token from the bearer header rather
        // than trusting $request->attributes->get('apiToken') alone: Laravel
        // sorts route middleware by its own internal $middlewarePriority
        // list, which can run ThrottleRequests before an app-defined
        // middleware like ApiTokenAuth even when ApiTokenAuth is listed
        // first in routes/api.php's own group array — so the attribute
        // isn't reliably set yet by the time this closure runs.
        // Features that call a paid third-party API on every request
        // (Google Places, OpenAI) without always charging credits first —
        // capped per user so a script can't run up the platform's bill.
        RateLimiter::for('paid-lookups', fn (Request $request) => [
            Limit::perMinute(20)->by('paid-lookups-min:'.($request->user()?->id ?? $request->ip())),
            Limit::perDay(400)->by('paid-lookups-day:'.($request->user()?->id ?? $request->ip())),
        ]);

        RateLimiter::for('support-chat', fn (Request $request) => [
            Limit::perMinute(20)->by('support-chat-min:'.($request->user()?->id ?? $request->ip())),
            Limit::perDay(200)->by('support-chat-day:'.($request->user()?->id ?? $request->ip())),
        ]);

        RateLimiter::for('api', function (Request $request) {
            $token = $request->attributes->get('apiToken')
                ?? ApiToken::findByPlainText((string) $request->bearerToken());
            $planSlug = $token?->user?->billableUser()->activeSubscription?->plan?->slug;
            $limits = config('api_billing.rate_limits', []);
            $perMinute = (int) ($limits[$planSlug] ?? $limits['default'] ?? 60);

            return Limit::perMinute($perMinute)->by($token?->id ?? $request->ip());
        });
    }
}
