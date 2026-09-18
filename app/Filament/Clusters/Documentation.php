<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/**
 * Groups every admin-facing "how this settings screen works" page under one
 * sidebar item with its own sub-navigation, instead of one long scrolling
 * page — see app/Filament/Pages/Documentation/* for the actual pages.
 * Visiting this cluster's own URL redirects straight to the first sub-page
 * (Filament\Clusters\Cluster::mount() does this automatically), so Overview
 * doubles as both "the documentation landing page" and "what you see if you
 * somehow hit the cluster URL directly".
 */
class Documentation extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Documentation';

    protected static ?int $navigationSort = 100;
}
