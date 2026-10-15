<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ReferralCampaign;
use App\Models\SiteSetting;
use App\Services\Referrals\MarketingKit\MarketingKit;
use App\Support\ReferralProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Links & campaigns: the partner's main referral link, the campaign link
 * builder (one labelled, tracked link per place they promote), ready-made
 * text links and brand assets.
 */
class LinksController extends Controller
{
    public const MAX_CAMPAIGNS = 100;

    public function index(Request $request, MarketingKit $kit): View
    {
        $user = $request->user();

        $campaigns = $user->referralCampaigns()
            ->withCount(['clicks', 'referrals', 'referrals as paying_count' => fn ($q) => $q->where('status', 'converted')])
            ->latest()
            ->get()
            ->map(fn (ReferralCampaign $campaign) => ['campaign' => $campaign, 'link' => $kit->campaignLink($user, $campaign)]);

        return view('partner.links', [
            'user' => $user,
            'campaigns' => $campaigns,
            'channels' => ReferralCampaign::channels(),
            'landingPages' => ReferralProgram::landingPages(),
            'links' => [
                'plain' => $user->referral_link,
                'email' => $kit->trackedLink($user, 'email', 'signature'),
                'bio' => $kit->trackedLink($user, 'social', 'bio'),
            ],
            'swipes' => $kit->swipesFor($user),
            'brand' => [
                'site_name' => SiteSetting::get('site_name', 'AffilStack'),
                'logo_rectangular' => SiteSetting::get('logo_rectangular_path'),
                'logo_square' => SiteSetting::get('logo_square_path'),
                'logo_rectangular_white' => SiteSetting::get('logo_rectangular_white_path'),
                'logo_square_white' => SiteSetting::get('logo_square_white_path'),
                'colors' => [
                    ['name' => 'Navy', 'hex' => strtoupper((string) SiteSetting::get('color_navy', '#0B1E3D'))],
                    ['name' => 'Blue', 'hex' => strtoupper((string) SiteSetting::get('color_primary', '#2452D9'))],
                    ['name' => 'Gold', 'hex' => strtoupper((string) SiteSetting::get('color_gold', '#C9A24A'))],
                    ['name' => 'White', 'hex' => '#FFFFFF'],
                ],
            ],
            'kitTabUrl' => fn (string $tab): string => route(PromoteController::routeForTab($tab)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'channel' => ['required', Rule::in(array_keys(ReferralCampaign::channels()))],
            'landing_path' => ['required', 'string', Rule::in(array_keys(ReferralProgram::landingPages()))],
        ]);

        if ($user->referralCampaigns()->count() >= self::MAX_CAMPAIGNS) {
            return back()->with('error', 'You can have up to '.self::MAX_CAMPAIGNS.' campaign links. Delete one you no longer use first.');
        }

        $base = Str::limit(Str::slug($data['name']), 50, '') ?: 'campaign';
        $slug = $base;

        for ($i = 2; $user->referralCampaigns()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $campaign = $user->referralCampaigns()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'channel' => $data['channel'],
            'landing_path' => $data['landing_path'],
        ]);

        return redirect()->route('partner.links')
            ->with('success', 'Campaign link "'.$campaign->name.'" is ready. Copy it below.')
            ->with('new_campaign', $campaign->id);
    }

    public function destroy(Request $request, ReferralCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->user_id === $request->user()->id, 404);

        $campaign->delete();

        return redirect()->route('partner.links')->with('success', 'Campaign link deleted. Sign-ups it already brought in stay credited to you.');
    }
}
