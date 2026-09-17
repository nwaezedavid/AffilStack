<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Intelligence\IntelligenceCentreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Task #7: the "Intelligence Centre" self-assessment dashboard — see
 * App\Services\Intelligence\IntelligenceCentreService. Owner-only, like
 * CRM/earnings/referrals/billing (see config('agency.seat_allowed_routes')),
 * since it reasons over billing/plan/referral data a team seat never sees
 * elsewhere either.
 */
class IntelligenceCentreController extends Controller
{
    public function index(): View
    {
        $report = auth()->user()->intelligenceCentreReport;

        return view('dashboard.intelligence-centre.index', compact('report'));
    }

    public function store(IntelligenceCentreService $service): RedirectResponse
    {
        try {
            $service->generate(auth()->user());
        } catch (InsufficientCreditsException) {
            return redirect()->route('intelligence-centre.index')
                ->with('error', 'Not enough credits to run an assessment. Upgrade your plan or buy a credit top-up.');
        } catch (AIGenerationException $e) {
            return redirect()->route('intelligence-centre.index')
                ->with('error', "Couldn't run the assessment right now: {$e->getMessage()}");
        }

        return redirect()->route('intelligence-centre.index')->with('success', 'Your Intelligence Centre assessment is ready.');
    }
}
