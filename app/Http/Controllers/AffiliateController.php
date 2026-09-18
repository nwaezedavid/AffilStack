<?php

namespace App\Http\Controllers;

use App\Services\Referrals\AffiliateApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function show(): View
    {
        return view('marketing.affiliate');
    }

    public function apply(Request $request, AffiliateApplicationService $applications): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'promotion_channels' => ['required', 'string', 'max:2000'],
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
