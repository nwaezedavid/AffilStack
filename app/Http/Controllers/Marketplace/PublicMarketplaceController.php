<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Support\Marketplace\MarketplaceCatalog;
use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * affilstack.com/marketplace — the approved add-ons, for anyone to browse.
 */
class PublicMarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(MarketplaceSettings::enabled(), 404);

        return view('marketplace.index', [
            'addons' => MarketplaceCatalog::browse($request),
            'types' => config('marketplace.types'),
            'categories' => config('marketplace.categories'),
        ]);
    }

    public function show(Addon $addon): View
    {
        abort_unless(MarketplaceSettings::enabled() && $addon->isListed(), 404);

        return view('marketplace.show', [
            'addon' => $addon->load('developer'),
            'version' => $addon->liveVersion,
        ]);
    }
}
