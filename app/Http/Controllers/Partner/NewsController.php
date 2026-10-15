<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerAnnouncement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Announcements from the AffilStack team. Opening the page marks every
 * announcement as read for this partner.
 */
class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $seenBefore = $user->partner_news_seen_at;

        $user->forceFill(['partner_news_seen_at' => now()])->save();

        return view('partner.news', [
            'announcements' => PartnerAnnouncement::query()->published()->latestFirst()->paginate(15),
            'seenBefore' => $seenBefore,
        ]);
    }
}
