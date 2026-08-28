<?php

namespace App\Providers;

use App\Services\AI\AIProvider;
use App\Services\AI\OpenAIProvider;
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
        //
    }
}
