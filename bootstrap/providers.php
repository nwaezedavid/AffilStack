<?php

use App\Providers\AnalyticsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TranslationServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AdminPanelProvider::class,
    // Agent 04: cookie consent + first-party analytics wiring.
    AnalyticsServiceProvider::class,
    // Feature 6: translation (Blade echo marking, translated emails).
    TranslationServiceProvider::class,
];
