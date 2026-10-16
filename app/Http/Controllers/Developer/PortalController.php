<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\View\View;

/**
 * The developer portal's public pages: what developers can build and
 * earn, and the documentation.
 */
class PortalController extends Controller
{
    /** Documentation pages, in reading order. */
    public const DOCS = [
        'getting-started' => 'Getting started',
        'connect' => 'Connect with AffilStack (OAuth)',
        'api' => 'Calling the API',
        'webhooks' => 'Webhooks',
        'template-packs' => 'Template packs',
        'automations' => 'Automation recipes',
        'review' => 'Review guidelines',
        'payouts' => 'Pricing & payouts',
    ];

    public function home(): View
    {
        return view('developer.home', [
            'share' => MarketplaceSettings::revenueSharePercent(),
            'holdDays' => MarketplaceSettings::holdDays(),
            'types' => config('marketplace.types'),
            'liveCount' => Addon::query()->listed()->count(),
        ]);
    }

    public function docs(?string $page = null): View
    {
        $page ??= 'getting-started';
        abort_unless(array_key_exists($page, self::DOCS), 404);

        return view('developer.docs.'.$page, [
            'pages' => self::DOCS,
            'page' => $page,
            'title' => self::DOCS[$page],
            'share' => MarketplaceSettings::revenueSharePercent(),
            'holdDays' => MarketplaceSettings::holdDays(),
            'scopes' => config('marketplace.scopes'),
            'events' => config('marketplace.webhook_events'),
            'kinds' => config('marketplace.template_kinds'),
            'platforms' => config('marketplace.automation_platforms'),
        ]);
    }
}
