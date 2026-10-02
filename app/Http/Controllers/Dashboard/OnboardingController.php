<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The welcome tour (partials/onboarding-tour). Finishing or skipping it is
 * remembered on the user, so it never auto-starts again; "Take the tour"
 * clears both and sends the user back to start it on demand.
 */
class OnboardingController extends Controller
{
    public function complete(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->forceFill(['onboarding_completed_at' => now()])->save();

        return $request->expectsJson()
            ? response()->json(['status' => 'completed'])
            : back();
    }

    public function dismiss(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->forceFill(['onboarding_dismissed_at' => now()])->save();

        return $request->expectsJson()
            ? response()->json(['status' => 'dismissed'])
            : back();
    }

    /**
     * Start the tour again — from the dashboard for a normal account, or
     * from wherever a team seat was (its dashboard is just a redirect).
     */
    public function restart(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['onboarding_completed_at' => null, 'onboarding_dismissed_at' => null])->save();

        if ($user->isSeat()) {
            return redirect()->to(url()->previous(route('dashboard')));
        }

        return redirect()->route('dashboard', ['tour' => 1]);
    }
}
