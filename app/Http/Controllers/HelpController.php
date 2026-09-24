<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use App\Models\SiteSetting;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        // Audit item #7 (caching/performance) — see FaqItem::publishedGrouped().
        $groups = FaqItem::publishedGrouped();
        $supportEmail = SiteSetting::get('support_email', 'support@affilstack.com');

        return view('marketing.help', compact('groups', 'supportEmail'));
    }
}
