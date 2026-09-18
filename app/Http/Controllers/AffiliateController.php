<?php

namespace App\Http\Controllers;

use App\Models\AffiliateApplication;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Services\Referrals\AffiliateApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The affiliate program's own public landing page — "anyone can sign-up to
 * become an affiliate without first becoming a user of the platform". See
 * routes/web.php for how this is served from either a real subdomain
 * (AFFILIATE_SUBDOMAIN) or a /affiliate prefix, and
 * AffiliateApplicationService for what happens to a submission.
 */
class AffiliateController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (! SiteSetting::flag('affiliate_program_enabled')) {
            return redirect()->route('home')->with('error', 'Our affiliate program isn\'t accepting new applications right now.');
        }

        return view('marketing.affiliate', [
            // Drives the earnings calculator with real, current plan
            // prices rather than hard-coded numbers that would drift out
            // of sync with PlanResource edits.
            'calculatorPlans' => Plan::activePublicList()->map(fn (Plan $plan) => [
                'name' => $plan->name,
                'priceMonthly' => $plan->priceMonthly(),
                'isFeatured' => $plan->is_featured,
            ])->values(),
            'audienceSizeOptions' => AffiliateApplication::audienceSizeOptions(),
            'experienceLevelOptions' => AffiliateApplication::experienceLevelOptions(),
        ]);
    }

    public function apply(Request $request, AffiliateApplicationService $applications): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'string', 'url', 'max:255'],
            'promotion_channels' => ['required', 'string', 'max:2000'],
            'audience_size' => ['required', 'string', Rule::in(array_keys(AffiliateApplication::audienceSizeOptions()))],
            'experience_level' => ['required', 'string', Rule::in(array_keys(AffiliateApplication::experienceLevelOptions()))],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $applications->submit($validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Thanks, {$validated['name']} — we've received your application and will review it shortly.");
    }
}
