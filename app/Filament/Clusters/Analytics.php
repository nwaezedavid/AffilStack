<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * The "statistics area" requested alongside the rest of this work: traffic
 * over time, traffic sources, popular pages, time-on-page, and paid users
 * by plan (see Analytics\Overview) plus the 404 Monitor (NotFoundLogResource,
 * regrouped under this cluster since it was requested as part of the same
 * statistics area). Its own top-level nav item, same pattern as the
 * Documentation cluster.
 */
class Analytics extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?int $navigationSort = -10;
}
