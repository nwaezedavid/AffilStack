<?php

use App\Providers\AnalyticsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AdminPanelProvider::class,
    // Agent 04: cookie consent + first-party analytics wiring.
    AnalyticsServiceProvider::class,
];
