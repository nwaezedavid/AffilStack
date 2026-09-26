<?php

namespace App\Providers;

use App\Models\ApiToken;
use App\Services\AI\AIProvider;
use App\Services\AI\OpenAIProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIProvider::class, function () {
            return match (config('ai.provider')) {
                default => new OpenAIProvider(
                    apiKey: (string) config('ai.openai.api_key'),
                    baseUrl: (string) config('ai.openai.base_url'),
                    textModel: (string) config('ai.openai.text_model'),
                    imageModel: (string) config('ai.openai.image_model'),
                    timeout: (int) config('ai.openai.timeout'),
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
